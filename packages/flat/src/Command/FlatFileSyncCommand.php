<?php

declare(strict_types=1);

namespace Pushword\Flat\Command;

use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Service\SharedOutputInterface;
use Pushword\Core\Service\TeeOutput;
use Pushword\Flat\FlatFileSync;
use Pushword\Flat\Service\DeferredExportProcessor;
use Pushword\Flat\Service\FlatChangeDetector;
use Pushword\Flat\Service\FlatLockManager;
use Pushword\Flat\Service\GitAutoCommitter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'pw:flat:sync',
    description: 'Syncing database toward flat yaml files (and json for media).'
)]
final readonly class FlatFileSyncCommand
{
    private const string PROCESS_TYPE = 'flat-sync';

    private const string COMMAND_PATTERN = 'pw:flat:sync';

    public function __construct(
        private FlatFileSync $flatFileSync,
        private Stopwatch $stopWatch,
        private BackgroundProcessManager $processManager,
        private ProcessOutputStorage $outputStorage,
        private FlatLockManager $lockManager,
        private FlatChangeDetector $changeDetector,
        private Filesystem $filesystem,
        private DeferredExportProcessor $deferredExportProcessor,
        private GitAutoCommitter $gitAutoCommitter,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Option(name: 'force', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Entity type to sync (page, media, conversation, all)', name: 'entity')]
        string $entity = 'all',
        #[Option(description: 'Sync mode: auto (detect), import (flat to db), export (db to flat)', name: 'mode', shortcut: 'm')]
        string $mode = 'auto',
        #[Option(description: 'Disable automatic database backup before import', name: 'no-backup')]
        bool $noBackup = false,
        #[Option(description: 'Consume pending export flag and run batched export', name: 'consume-pending')]
        bool $consumePending = false,
    ): int {
        if ($consumePending) {
            return $this->handleConsumePending($input, $output);
        }

        // Normalize entity to lowercase to avoid case-sensitivity issues
        $entity = strtolower($entity);

        // Check for webhook lock - blocks sync during external editing workflow
        if ($this->lockManager->isWebhookLocked($host)) {
            $lockInfo = $this->lockManager->getLockInfo($host);
            $remainingTime = $this->lockManager->getRemainingTime($host);
            $output->writeln('<error>Sync blocked: webhook lock active.</error>');
            $output->writeln(\sprintf(
                '<comment>Locked by: %s | Reason: %s | Remaining: %ds</comment>',
                $lockInfo['lockedByUser'] ?? 'unknown',
                $lockInfo['reason'] ?? 'N/A',
                $remainingTime,
            ));
            $output->writeln('<info>CI/CD should retry after lock is released.</info>');

            return Command::FAILURE;
        }

        // Check if same process type is already running (via PID file)
        $pidFile = $this->processManager->getPidFilePath(self::PROCESS_TYPE);
        $this->processManager->cleanupStaleProcess($pidFile);
        $processInfo = $this->processManager->getProcessInfo($pidFile);

        if ($processInfo['isRunning']) {
            $output->writeln('<error>Flat sync is already running (PID: '.$processInfo['pid'].').</error>');

            return Command::FAILURE;
        }

        // Register this process and setup shared output
        $this->processManager->registerProcess($pidFile, self::COMMAND_PATTERN);

        // Only clear storage if not already initialized by web controller
        $currentStatus = $this->outputStorage->getStatus(self::PROCESS_TYPE);
        if ('running' !== $currentStatus) {
            $this->outputStorage->clear(self::PROCESS_TYPE);
            $this->outputStorage->setStatus(self::PROCESS_TYPE, 'running');
        }

        // Create tee output to write to both console and shared storage
        $sharedOutput = new SharedOutputInterface($this->outputStorage, self::PROCESS_TYPE);
        $teeOutput = new TeeOutput([$output, $sharedOutput]);

        try {
            $teeOutput->writeln('<comment>PID: '.getmypid().'</comment>');
            $this->stopWatch->start('sync');

            // Set tee output and stopwatch for progress reporting
            $this->flatFileSync->setOutput($teeOutput);
            $this->flatFileSync->setStopwatch($this->stopWatch);

            // Backup database before import (unless disabled)
            $willImport = 'import' === $mode || 'auto' === $mode;
            if ($willImport && ! $noBackup && $this->filesystem->exists('var/app.db')) {
                $backupFileName = 'var/app.db~'.date('YmdHis');
                $this->filesystem->copy('var/app.db', $backupFileName);
                $teeOutput->writeln(\sprintf('<comment>Database backup created: %s</comment>', $backupFileName));
            }

            if (null !== $host) {
                $this->syncHost($host, $force, $entity, $mode);
            } else {
                foreach ($this->flatFileSync->getHosts() as $appHost) {
                    $teeOutput->writeln(\sprintf('<info>Syncing %s...</info>', $appHost));
                    $this->syncHost($appHost, $force, $entity, $mode);
                }
            }

            $event = $this->stopWatch->stop('sync');
            $duration = $event->getDuration();

            $this->displaySummary(new SymfonyStyle($input, $teeOutput), $duration, $mode);

            // Print timing breakdown
            $this->printTimingBreakdown($teeOutput);

            $teeOutput->writeln(\sprintf('<comment>:: peak memory: %.1f MB</comment>', memory_get_peak_usage(true) / 1024 / 1024));

            // Release auto-lock and invalidate change detector cache for synced hosts
            $syncedHosts = null !== $host ? [$host] : $this->flatFileSync->getHosts();
            foreach ($syncedHosts as $syncedHost) {
                $this->lockManager->releaseAutoLock($syncedHost);
                $this->changeDetector->invalidateCache($syncedHost);
            }

            $this->outputStorage->setStatus(self::PROCESS_TYPE, 'completed');

            return Command::SUCCESS;
        } finally {
            // Clean up PID file
            $this->processManager->unregisterProcess($pidFile);
        }
    }

    private function handleConsumePending(InputInterface $input, OutputInterface $output): int
    {
        $pending = $this->deferredExportProcessor->readPendingFlag();

        if (null === $pending) {
            $output->writeln('<info>No pending export flag found.</info>');

            return Command::SUCCESS;
        }

        $entityTypes = $pending['entityTypes'];
        $hosts = $pending['hosts'];

        $entity = 1 === \count($entityTypes) ? $entityTypes[0] : 'all';

        $output->writeln(\sprintf(
            '<info>Consuming pending export: entities=%s, hosts=%s</info>',
            implode(',', $entityTypes) ?: 'all',
            implode(',', $hosts) ?: 'all',
        ));

        // Clear flag before export to avoid losing flags written during export
        $this->deferredExportProcessor->clearPendingFlag();

        $hostsToExport = [] !== $hosts ? $hosts : [null];
        foreach ($hostsToExport as $host) {
            $result = $this->__invoke($input, $output, $host, force: true, entity: $entity, mode: 'export');
            if (Command::SUCCESS !== $result) {
                return $result;
            }
        }

        // Git auto-commit after successful export
        if ($this->gitAutoCommitter->isEnabled()) {
            $committed = $this->gitAutoCommitter->commitIfChanges();
            if ($committed) {
                $output->writeln('<info>Git auto-commit: changes committed and pushed.</info>');
            }
        }

        return Command::SUCCESS;
    }

    private function syncHost(string $host, bool $force, string $entity, string $mode): void
    {
        match ($mode) {
            'import' => $this->flatFileSync->import($host, $entity, $force),
            'export' => $this->flatFileSync->export($host, force: $force, entity: $entity),
            default => $this->flatFileSync->sync($host, $force, null, $entity),
        };
    }

    private function displaySummary(SymfonyStyle $io, float $duration, string $mode): void
    {
        $mediaSync = $this->flatFileSync->mediaSync;
        $pageSync = $this->flatFileSync->pageSync;

        $hasImportOps = $mediaSync->getImportedCount() > 0
            || $pageSync->getImportedCount() > 0
            || $mediaSync->getDeletedCount() > 0
            || $pageSync->getDeletedCount() > 0;

        $hasExportOps = $mediaSync->getExportedCount() > 0 || $pageSync->getExportedCount() > 0;

        // Use requested mode unless it's 'auto', then detect from operations
        $displayMode = 'auto' === $mode
            ? ($hasImportOps ? 'import' : ($hasExportOps ? 'export' : 'auto'))
            : $mode;

        if (! $hasImportOps && ! $hasExportOps) {
            $io->success(\sprintf('Sync completed (%s mode - no changes detected). (%dms)', $displayMode, $duration));

            return;
        }

        $io->success(\sprintf('Sync completed (%s mode). (%dms)', $displayMode, $duration));

        if ($hasImportOps) {
            $io->table(['Type', 'Imported', 'Skipped', 'Deleted'], [
                ['Media', $mediaSync->getImportedCount(), $mediaSync->getSkippedCount(), $mediaSync->getDeletedCount()],
                ['Pages', $pageSync->getImportedCount(), $pageSync->getSkippedCount(), $pageSync->getDeletedCount()],
            ]);
        }

        if ($hasExportOps) {
            $io->table(['Type', 'Exported', 'Skipped'], [
                ['Media', $mediaSync->getExportedCount(), 0],
                ['Pages', $pageSync->getExportedCount(), $pageSync->getExportSkippedCount()],
            ]);
        }
    }

    private function printTimingBreakdown(OutputInterface $output): void
    {
        $sections = $this->stopWatch->getSections();
        $timings = [];

        $allowedEvents = ['media.sync', 'page.sync', 'conversation.sync'];

        foreach ($sections as $section) {
            foreach ($section->getEvents() as $name => $event) {
                if (\in_array($name, $allowedEvents, true)) {
                    $timings[$name] = ($timings[$name] ?? 0) + $event->getDuration();
                }
            }
        }

        if ([] === $timings) {
            return;
        }

        arsort($timings);

        $parts = [];
        foreach ($timings as $name => $duration) {
            $shortName = match ($name) {
                'media.sync' => 'media',
                'page.sync' => 'pages',
                'conversation.sync' => 'conversation', // @phpstan-ignore match.alwaysTrue
                default => $name,
            };
            $parts[] = \sprintf('%s: %dms', $shortName, $duration);
        }

        $output->writeln('<comment>⏱ '.implode(' | ', $parts).'</comment>');
    }
}
