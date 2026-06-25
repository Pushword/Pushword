<?php

namespace Pushword\Flat\Command;

use Pushword\Core\Command\AgentOutputTrait;
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
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'pw:flat:sync',
    description: 'Syncing database toward flat yaml files (and json for media).'
)]
final class FlatFileSyncCommand
{
    use AgentOutputTrait;

    private const string PROCESS_TYPE = 'flat-sync';

    private const string COMMAND_PATTERN = 'pw:flat:sync';

    private bool $agentMode = false;

    /** Suppress agent JSON emission during nested (consume-pending) recursion. */
    private bool $silent = false;

    public function __construct(
        private readonly FlatFileSync $flatFileSync,
        private readonly Stopwatch $stopWatch,
        private readonly BackgroundProcessManager $processManager,
        private readonly ProcessOutputStorage $outputStorage,
        private readonly FlatLockManager $lockManager,
        private readonly FlatChangeDetector $changeDetector,
        private readonly Filesystem $filesystem,
        private readonly DeferredExportProcessor $deferredExportProcessor,
        private readonly GitAutoCommitter $gitAutoCommitter,
    ) {
    }

    /** @param string[] $page */
    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Option(name: 'force', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Entity type to sync (page, media, conversation, snippet, all)', name: 'entity')]
        string $entity = 'all',
        #[Option(description: 'Sync mode: auto (detect), import (flat to db), export (db to flat)', name: 'mode', shortcut: 'm')]
        string $mode = 'auto',
        #[Option(description: 'Create a database backup before import', name: 'backup')]
        bool $backup = false,
        #[Option(description: 'Consume pending export flag and run batched export', name: 'consume-pending')]
        bool $consumePending = false,
        #[Option(description: 'Page slug(s) to sync (repeatable, implies --entity=page)', name: 'page')]
        array $page = [],
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);

        if ($consumePending) {
            return $this->handleConsumePending($input, $output);
        }

        // Normalize entity to lowercase to avoid case-sensitivity issues
        $entity = strtolower($entity);

        if ([] !== $page) {
            $entity = 'page';
        }

        // Check for webhook lock - blocks sync during external editing workflow
        if ($this->lockManager->isWebhookLocked($host)) {
            if ($this->agentMode) {
                if (! $this->silent) {
                    $this->writeAgentJson($output, ['tool' => 'pw:flat:sync', 'result' => 'blocked', 'message' => 'webhook lock active']);
                }

                return Command::FAILURE;
            }

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
            if ($this->agentMode) {
                if (! $this->silent) {
                    $this->writeAgentJson($output, ['tool' => 'pw:flat:sync', 'result' => 'running', 'pid' => $processInfo['pid']]);
                }
            } else {
                $output->writeln('<error>Flat sync is already running (PID: '.$processInfo['pid'].').</error>');
            }

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
            if (! $this->agentMode) {
                $teeOutput->writeln('<comment>PID: '.getmypid().'</comment>');
            }

            $this->stopWatch->start('sync');

            // Set tee output and stopwatch for progress reporting (silenced for agents)
            $this->flatFileSync->setOutput($this->agentMode ? new NullOutput() : $teeOutput);
            $this->flatFileSync->setStopwatch($this->stopWatch);

            // Backup database before import (unless disabled)
            $willImport = 'import' === $mode || 'auto' === $mode;
            if ($willImport && $backup && $this->filesystem->exists('var/app.db')) {
                $backupFileName = 'var/app.db~'.date('YmdHis');
                $this->filesystem->copy('var/app.db', $backupFileName);
                if (! $this->agentMode) {
                    $teeOutput->writeln(\sprintf('<comment>Database backup created: %s</comment>', $backupFileName));
                }
            }

            if (null !== $host) {
                $this->syncHost($host, $force, $entity, $mode, $page);
            } else {
                foreach ($this->flatFileSync->getHosts() as $appHost) {
                    if (! $this->agentMode) {
                        $teeOutput->writeln(\sprintf('<info>Syncing %s...</info>', $appHost));
                    }

                    $this->syncHost($appHost, $force, $entity, $mode, $page);
                }
            }

            $event = $this->stopWatch->stop('sync');
            $duration = $event->getDuration();

            if ($this->agentMode) {
                if (! $this->silent) {
                    $this->writeAgentJson($teeOutput, $this->buildSyncSummary($duration, $mode));
                }
            } else {
                $this->displaySummary(new SymfonyStyle($input, $teeOutput), $duration, $mode);
                $this->printTimingBreakdown($teeOutput);
                $teeOutput->writeln(\sprintf('<comment>:: peak memory: %.1f MB</comment>', memory_get_peak_usage(true) / 1024 / 1024));
            }

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
            if ($this->agentMode) {
                $this->writeAgentJson($output, ['tool' => 'pw:flat:sync', 'result' => 'passed', 'mode' => 'consume-pending', 'pending' => false]);
            } else {
                $output->writeln('<info>No pending export flag found.</info>');
            }

            return Command::SUCCESS;
        }

        $entityTypes = $pending['entityTypes'];
        $hosts = $pending['hosts'];

        $entity = 1 === \count($entityTypes) ? $entityTypes[0] : 'all';

        if (! $this->agentMode) {
            $output->writeln(\sprintf(
                '<info>Consuming pending export: entities=%s, hosts=%s</info>',
                implode(',', $entityTypes) ?: 'all',
                implode(',', $hosts) ?: 'all',
            ));
        }

        // Clear flag before export to avoid losing flags written during export
        $this->deferredExportProcessor->clearPendingFlag();

        // Suppress the nested invocations' own output; emit a single summary here.
        $innerFormat = $this->agentMode ? 'agent' : 'text';
        $hostsToExport = [] !== $hosts ? $hosts : [null];
        $this->silent = true;

        try {
            foreach ($hostsToExport as $host) {
                $result = $this->__invoke($input, $output, $host, force: true, entity: $entity, mode: 'export', format: $innerFormat);
                if (Command::SUCCESS !== $result) {
                    if ($this->agentMode) {
                        $this->writeAgentJson($output, ['tool' => 'pw:flat:sync', 'result' => 'failed', 'mode' => 'consume-pending', 'host' => $host]);
                    }

                    return $result;
                }
            }
        } finally {
            $this->silent = false;
        }

        // Git auto-commit after successful export
        $committed = false;
        if ($this->gitAutoCommitter->isEnabled()) {
            $committed = $this->gitAutoCommitter->commitIfChanges();
            if ($committed && ! $this->agentMode) {
                $output->writeln('<info>Git auto-commit: changes committed and pushed.</info>');
            }
        }

        if ($this->agentMode) {
            $this->writeAgentJson($output, [
                'tool' => 'pw:flat:sync',
                'result' => 'passed',
                'mode' => 'consume-pending',
                'hosts' => $hosts,
                'entities' => $entityTypes,
                'committed' => $committed,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSyncSummary(float $duration, string $mode): array
    {
        $mediaSync = $this->flatFileSync->mediaSync;
        $pageSync = $this->flatFileSync->pageSync;
        $snippetSync = $this->flatFileSync->snippetSync;

        $imported = $mediaSync->getImportedCount() + $pageSync->getImportedCount() + ($snippetSync?->getImportedCount() ?? 0);
        $exported = $mediaSync->getExportedCount() + $pageSync->getExportedCount() + ($snippetSync?->getExportedCount() ?? 0);
        $deleted = $mediaSync->getDeletedCount() + $pageSync->getDeletedCount() + ($snippetSync?->getDeletedCount() ?? 0);

        $displayMode = match (true) {
            'auto' !== $mode => $mode,
            $imported > 0 => 'import',
            $exported > 0 => 'export',
            default => 'auto',
        };

        return [
            'tool' => 'pw:flat:sync',
            'result' => 'passed',
            'mode' => $displayMode,
            'imported' => $imported,
            'exported' => $exported,
            'deleted' => $deleted,
            'yaml_errors' => $pageSync->getYamlErrorCount(),
            'duration_ms' => (int) $duration,
        ];
    }

    /** @param string[] $pageSlugs */
    private function syncHost(string $host, bool $force, string $entity, string $mode, array $pageSlugs = []): void
    {
        match ($mode) {
            'import' => $this->flatFileSync->import($host, $entity, $force, $pageSlugs),
            'export' => $this->flatFileSync->export($host, force: $force, entity: $entity, pageSlugs: $pageSlugs),
            default => $this->flatFileSync->sync($host, $force, null, $entity, $pageSlugs),
        };
    }

    private function displaySummary(SymfonyStyle $io, float $duration, string $mode): void
    {
        $mediaSync = $this->flatFileSync->mediaSync;
        $pageSync = $this->flatFileSync->pageSync;
        $snippetSync = $this->flatFileSync->snippetSync;

        $hasImportOps = $mediaSync->getImportedCount() > 0
            || $pageSync->getImportedCount() > 0
            || $mediaSync->getDeletedCount() > 0
            || $pageSync->getDeletedCount() > 0
            || ($snippetSync?->getImportedCount() ?? 0) > 0
            || ($snippetSync?->getDeletedCount() ?? 0) > 0;

        $hasExportOps = $mediaSync->getExportedCount() > 0
            || $pageSync->getExportedCount() > 0
            || ($snippetSync?->getExportedCount() ?? 0) > 0;

        // Use requested mode unless it's 'auto', then detect from operations
        $displayMode = 'auto' === $mode
            ? ($hasImportOps ? 'import' : ($hasExportOps ? 'export' : 'auto'))
            : $mode;

        $yamlErrors = $pageSync->getYamlErrorCount();

        if (! $hasImportOps && ! $hasExportOps && 0 === $yamlErrors) {
            $io->success(\sprintf('Sync completed (%s mode - no changes detected). (%dms)', $displayMode, $duration));

            return;
        }

        $io->success(\sprintf('Sync completed (%s mode). (%dms)', $displayMode, $duration));

        if ($hasImportOps) {
            $importRows = [
                ['Media', $mediaSync->getImportedCount(), $mediaSync->getSkippedCount(), $mediaSync->getDeletedCount()],
                ['Pages', $pageSync->getImportedCount(), $pageSync->getSkippedCount(), $pageSync->getDeletedCount()],
            ];
            if (null !== $snippetSync) {
                $importRows[] = ['Snippets', $snippetSync->getImportedCount(), $snippetSync->getSkippedCount(), $snippetSync->getDeletedCount()];
            }

            $io->table(['Type', 'Imported', 'Skipped', 'Deleted'], $importRows);
        }

        if ($hasExportOps) {
            $exportRows = [
                ['Media', $mediaSync->getExportedCount(), 0],
                ['Pages', $pageSync->getExportedCount(), $pageSync->getExportSkippedCount()],
            ];
            if (null !== $snippetSync) {
                $exportRows[] = ['Snippets', $snippetSync->getExportedCount(), $snippetSync->getSkippedCount()];
            }

            $io->table(['Type', 'Exported', 'Skipped'], $exportRows);
        }

        if ($yamlErrors > 0) {
            $io->warning(\sprintf('%d file(s) skipped due to YAML front matter errors. Run `pw:flat:lint` for details.', $yamlErrors));
        }
    }

    private function printTimingBreakdown(OutputInterface $output): void
    {
        $sections = $this->stopWatch->getSections();
        $timings = [];

        $allowedEvents = ['media.sync', 'page.sync', 'conversation.sync', 'snippet.sync', 'media.detection', 'page.detection'];

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

        // Build display with detection sub-timings
        $parts = [];
        foreach (['media.sync', 'page.sync', 'conversation.sync', 'snippet.sync'] as $name) {
            if (! isset($timings[$name])) {
                continue;
            }

            $shortName = match ($name) {
                'media.sync' => 'media',
                'page.sync' => 'pages',
                'conversation.sync' => 'conversation',
                'snippet.sync' => 'snippets',
            };

            $detectionKey = str_replace('.sync', '.detection', $name);
            $detectionMs = $timings[$detectionKey] ?? null;

            $parts[] = null !== $detectionMs
                ? \sprintf('%s: %dms (detection: %dms)', $shortName, $timings[$name], $detectionMs)
                : \sprintf('%s: %dms', $shortName, $timings[$name]);
        }

        $output->writeln('<comment>⏱ '.implode(' | ', $parts).'</comment>');
    }
}
