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

#[AsCommand(
    name: 'pw:flat:sync',
    description: 'Syncing database toward flat yaml files (and json for media).'
)]
final readonly class FlatFileSyncCommand
{
    public function __construct(
        private FlatFileSync $flatFileSync,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Option(name: 'force', shortcut: 'f')]
        bool $force = false,
    ): int {
        $this->flatFileSync->sync($host, $force);

        $this->displaySummary(new SymfonyStyle($input, $output));

        return Command::SUCCESS;
    }

    private function displaySummary(SymfonyStyle $io): void
    {
        $mediaImported = $this->flatFileSync->mediaSync->getImportedCount();
        $pageImported = $this->flatFileSync->pageSync->getImportedCount();

        // Only show table if there was an import operation
        if (0 === $mediaImported && 0 === $pageImported
            && 0 === $this->flatFileSync->mediaSync->getDeletedCount()
            && 0 === $this->flatFileSync->pageSync->getDeletedCount()
        ) {
            $io->success('Sync completed (export mode - no changes detected)');

            return;
        }

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
    }
}
