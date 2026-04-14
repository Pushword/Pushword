<?php

namespace Pushword\Flat\Command;

use Pushword\Flat\FlatFileSync;
use Pushword\Flat\Service\FlatChangeDetector;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'pw:flat:watch',
    description: 'Watch flat files for changes and auto-sync to database.'
)]
final readonly class FlatFileWatchCommand
{
    public function __construct(
        private FlatFileSync $flatFileSync,
        private FlatChangeDetector $changeDetector,
        private FlatLockManager $lockManager,
        private string $projectDir,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Poll interval in seconds', name: 'interval')]
        float $interval = 0.5,
        #[Option(description: 'Sync mode: auto (detect direction) or import (flat to db only)', name: 'mode', shortcut: 'm')]
        string $mode = 'auto',
        #[Option(description: 'Enable live reload (writes signal file for browser auto-refresh)', name: 'live-reload')]
        bool $liveReload = false,
        #[Option(description: 'Max polling cycles (0 = infinite, for testing)', name: 'max-cycles')]
        int $maxCycles = 0,
    ): int {
        $signalFile = $this->projectDir.'/public/_flat-reload.txt';

        if ($liveReload) {
            $this->filesystem->dumpFile($signalFile, (string) microtime(true));
        }

        $hosts = $this->flatFileSync->getHosts();
        $output->writeln(\sprintf('<info>Watching %d host(s) every %.1fs...</info>', \count($hosts), $interval));

        $this->flatFileSync->setOutput($output);
        $cycle = 0;

        while (0 === $maxCycles || $cycle < $maxCycles) {
            ++$cycle;

            foreach ($hosts as $host) {
                if ($this->lockManager->isWebhookLocked($host)) {
                    continue;
                }

                if ($this->lockManager->isManualLock($host)) {
                    continue;
                }

                $changes = $this->changeDetector->forceCheck($host);

                if (! $changes['hasChanges']) {
                    continue;
                }

                $output->writeln(\sprintf('<comment>[%s] Changes detected on %s: %s</comment>', date('H:i:s'), $host, implode(', ', $changes['entityTypes'])));

                match ($mode) {
                    'import' => $this->flatFileSync->import($host),
                    default => $this->flatFileSync->sync($host),
                };

                $this->lockManager->releaseAutoLock($host);
                $this->changeDetector->invalidateCache($host);

                $output->writeln(\sprintf('<info>[%s] Sync completed for %s</info>', date('H:i:s'), $host));

                if ($liveReload) {
                    $this->filesystem->dumpFile($signalFile, (string) microtime(true));
                }

                if (\in_array('media', $changes['entityTypes'], true)) {
                    $this->startImageCacheInBackground($output);
                }
            }

            if (0 === $maxCycles || $cycle < $maxCycles) {
                usleep((int) ($interval * 1_000_000));
            }
        }

        return Command::SUCCESS;
    }

    private function startImageCacheInBackground(OutputInterface $output): void
    {
        $consolePath = $this->projectDir.'/bin/console';
        if (! $this->filesystem->exists($consolePath)) {
            return;
        }

        $process = new Process(['php', $consolePath, 'pw:image:cache']);
        $process->setWorkingDirectory($this->projectDir);
        $process->disableOutput();
        $process->start();

        $output->writeln(\sprintf('<comment>[%s] Started pw:image:cache in background (PID %d)</comment>', date('H:i:s'), $process->getPid()));
    }
}
