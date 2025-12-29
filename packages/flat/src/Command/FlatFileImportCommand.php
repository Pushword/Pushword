<?php

namespace Pushword\Flat\Command;

use Pushword\Flat\FlatFileSync;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'pw:flat:import', description: 'Syncing flat file inside database.')]
final class FlatFileImportCommand
{
    public function __construct(
        protected FlatFileSync $flatFileSync,
        private readonly Filesystem $fs,
    ) {
    }

    public function __invoke(
        #[Argument(name: 'host')]
        ?string $host,
        InputInterface $input,
        OutputInterface $output,
        #[Option(name: 'force', shortcut: 'f')]
        string $force = 'all',
        #[Option(description: 'Skip adding IDs to markdown files and CSV indexes after import', name: 'skip-id')]
        bool $skipId = false,
    ): int {
        $io = new SymfonyStyle($input, $output);

        if ($this->fs->exists('var/app.db')) {
            $backupFileName = 'var/app.db~'.date('YmdHis');
            $this->fs->copy('var/app.db', $backupFileName);
        }

        $output->writeln('Import will start in few seconds...');

        if ('all' === $force) {
            $this->flatFileSync->import($host, $skipId);
        } elseif ('media' === $force) {
            $output->writeln('-- only media');
            $this->flatFileSync->mediaSync->import($host);
        } elseif ('page' === $force) {
            $output->writeln('-- only page');
            $this->flatFileSync->pageSync->import($host, $skipId);
        }

        // Display warnings for missing media files
        $missingFiles = $this->flatFileSync->mediaSync->getMissingFiles();
        if ([] !== $missingFiles) {
            $io->warning('Some media files were not found and skipped:');
            $io->listing($missingFiles);
        }

        $this->displaySummary($io, $force);

        return Command::SUCCESS;
    }

    private function displaySummary(SymfonyStyle $io, string $force): void
    {
        $rows = [];

        if ('all' === $force || 'media' === $force) {
            $rows[] = [
                'Media',
                $this->flatFileSync->mediaSync->getImportedCount(),
                $this->flatFileSync->mediaSync->getSkippedCount(),
                $this->flatFileSync->mediaSync->getDeletedCount(),
            ];
        }

        if ('all' === $force || 'page' === $force) {
            $rows[] = [
                'Pages',
                $this->flatFileSync->pageSync->getImportedCount(),
                $this->flatFileSync->pageSync->getSkippedCount(),
                $this->flatFileSync->pageSync->getDeletedCount(),
            ];
        }

        $io->table(['Type', 'Imported', 'Skipped', 'Deleted'], $rows);
    }
}
