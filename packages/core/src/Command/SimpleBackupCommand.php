<?php

namespace Pushword\Core\Command;

use Pushword\Core\Service\SqliteBackupManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pw:backup', description: 'Create, restore, or clean SQLite database backups.')]
final readonly class SimpleBackupCommand
{
    public function __construct(
        private SqliteBackupManager $backupManager,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option(name: 'create', shortcut: 'c')]
        bool $create = false,
        #[Option(description: 'Remove old backups, keeping only the most recent ones', name: 'clean')]
        bool $clean = false
    ): int {
        if (! $this->backupManager->isSupported()) {
            $output->writeln('<error>pw:backup supports SQLite databases only. Use the backup tools provided by your database server.</error>');

            return Command::FAILURE;
        }

        if ($create) {
            $this->createBackup($output);

            return Command::SUCCESS;
        }

        if ($clean) {
            $this->cleanBackups($output);

            return Command::SUCCESS;
        }

        $this->restoreLastBackup($output);

        return Command::SUCCESS;
    }

    public function createBackup(?OutputInterface $output = null): void
    {
        $backupFileName = $this->backupManager->create();
        $output?->writeln('Backup created: '.$backupFileName);
    }

    public function restoreLastBackup(?OutputInterface $output = null): void
    {
        $lastBackup = $this->backupManager->restoreLatest();
        if (null === $lastBackup) {
            $output?->writeln('<error>No backup files found</error>');

            return;
        }

        $output?->writeln('Restored from: '.$lastBackup);
    }

    public function cleanBackups(?OutputInterface $output = null, int $keep = 1): void
    {
        $backupFiles = $this->backupManager->backupFiles();
        if ([] === $backupFiles) {
            $output?->writeln('No backup files to clean');

            return;
        }

        $filesToDelete = $this->backupManager->clean($keep);

        if ([] === $filesToDelete) {
            $output?->writeln('No old backups to remove (keeping '.$keep.' most recent)');

            return;
        }

        foreach ($filesToDelete as $file) {
            $output?->writeln('Removed: '.$file);
        }

        $output?->writeln(\sprintf('Cleaned %d old backup(s), kept %d most recent', \count($filesToDelete), min($keep, \count($backupFiles))));
    }
}
