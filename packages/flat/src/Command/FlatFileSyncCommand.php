<?php

declare(strict_types=1);

namespace Pushword\Flat\Command;

use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Service\SharedOutputInterface;
use Pushword\Core\Service\TeeOutput;
use Pushword\Flat\FlatFileSync;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
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
    ): int {
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

            if (null !== $host) {
                $this->flatFileSync->sync($host, $force, null, $entity);
            } else {
                foreach ($this->flatFileSync->getHosts() as $appHost) {
                    $teeOutput->writeln(\sprintf('<info>Syncing %s...</info>', $appHost));
                    $this->flatFileSync->sync($appHost, $force, null, $entity);
                }
            }

            $event = $this->stopWatch->stop('sync');
            $duration = $event->getDuration();

            $this->displaySummary(new SymfonyStyle($input, $teeOutput), $duration);

            // Print timing breakdown
            $this->printTimingBreakdown($teeOutput);

            $this->outputStorage->setStatus(self::PROCESS_TYPE, 'completed');

            return Command::SUCCESS;
        } finally {
            // Clean up PID file
            $this->processManager->unregisterProcess($pidFile);
        }
    }

    private function displaySummary(SymfonyStyle $io, float $duration): void
    {
        $mediaImported = $this->flatFileSync->mediaSync->getImportedCount();
        $pageImported = $this->flatFileSync->pageSync->getImportedCount();
        $mediaExported = $this->flatFileSync->mediaSync->getExportedCount();
        $pageExported = $this->flatFileSync->pageSync->getExportedCount();

        $isImportMode = $mediaImported > 0 || $pageImported > 0
            || $this->flatFileSync->mediaSync->getDeletedCount() > 0
            || $this->flatFileSync->pageSync->getDeletedCount() > 0;

        $isExportMode = $mediaExported > 0 || $pageExported > 0;

        if (! $isImportMode && ! $isExportMode) {
            $io->success(\sprintf('Sync completed (export mode - no changes detected). (%dms)', $duration));

            return;
        }

        if ($isImportMode) {
            $io->success(\sprintf('Sync completed (import mode). (%dms)', $duration));
            $io->table(['Type', 'Imported', 'Skipped', 'Deleted'], [
                [
                    'Media',
                    $this->flatFileSync->mediaSync->getImportedCount(),
                    $this->flatFileSync->mediaSync->getSkippedCount(),
                    $this->flatFileSync->mediaSync->getDeletedCount(),
                ],
                [
                    'Pages',
                    $this->flatFileSync->pageSync->getImportedCount(),
                    $this->flatFileSync->pageSync->getSkippedCount(),
                    $this->flatFileSync->pageSync->getDeletedCount(),
                ],
            ]);

            return;
        }

        $io->success(\sprintf('Sync completed (export mode). (%dms)', $duration));
        $io->table(['Type', 'Exported', 'Skipped'], [
            [
                'Media',
                $mediaExported,
                0,
            ],
            [
                'Pages',
                $pageExported,
                $this->flatFileSync->pageSync->getExportSkippedCount(),
            ],
        ]);
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
