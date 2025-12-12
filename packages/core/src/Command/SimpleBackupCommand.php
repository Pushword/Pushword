<?php

namespace Pushword\Core\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'pw:backup', description: 'Restore the last backup of the database.')]
final readonly class SimpleBackupCommand
{
    public function __construct(
        private Filesystem $fs,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option(name: 'create', shortcut: 'c')]
        bool $create = false,
        #[Option(name: 'clean', description: 'Remove old backups, keeping only the most recent ones')]
        bool $clean = false
    ): int {
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
        $backupFileName = 'var/app.db~'.date('YmdHis');
        $this->fs->copy('var/app.db', $backupFileName);
        $output?->writeln('Backup created: '.$backupFileName);
    }

    public function restoreLastBackup(?OutputInterface $output = null): void
    {
        $backupFiles = glob('var/app.db~*');
        if ([] === $backupFiles || false === $backupFiles) {
            $output?->writeln('<error>No backup files found</error>');

            return;
        }

        sort($backupFiles);
        $lastBackup = end($backupFiles);

        $this->fs->copy($lastBackup, 'var/app.db', true);
        $output?->writeln('Restored from: '.$lastBackup);
    }

    public function cleanBackups(?OutputInterface $output = null, int $keep = 1): void
    {
        $backupFiles = glob('var/app.db~*');
        if ([] === $backupFiles || false === $backupFiles) {
            $output?->writeln('No backup files to clean');

            return;
        }

        rsort($backupFiles);
        $filesToDelete = \array_slice($backupFiles, $keep);

        if ([] === $filesToDelete) {
            $output?->writeln('No old backups to remove (keeping '.$keep.' most recent)');

            return;
        }

        foreach ($filesToDelete as $file) {
            $this->fs->remove($file);
            $output?->writeln('Removed: '.$file);
        }

        $output?->writeln(\sprintf('Cleaned %d old backup(s), kept %d most recent', \count($filesToDelete), min($keep, \count($backupFiles))));
    }
}
