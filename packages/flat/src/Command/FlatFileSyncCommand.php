<?php

namespace Pushword\Flat\Command;

use Pushword\Flat\FlatFileExporter;
use Pushword\Flat\FlatFileImporter;
use Pushword\Flat\FlatFileSync;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @template T of object
 */
#[AsCommand(
    name: 'pw:flat:sync',
    description: 'Syncing database toward flat yaml files (and json for media).'
)]
final readonly class FlatFileSyncCommand
{
    /**
     * @param FlatFileImporter<T> $importer
     */
    public function __construct(
        private FlatFileSync $flatFileSync,
        private FlatFileImporter $importer,
        private FlatFileExporter $exporter,
    ) {
    }

    public function __invoke(
        #[Argument(name: 'host')]
        ?string $host,
        #[Option(name: 'force', shortcut: 'f')]
        bool $force,
        OutputInterface $output
    ): int {
        if ($this->flatFileSync->mustImport($host)) {
            $output->writeln('Import detected - running import...');
            $duration = $this->importer->run($host);
            $output->writeln('Import took '.$duration.' ms.');

            return Command::SUCCESS;
        }

        $output->writeln('Export detected - running export...');
        $duration = $this->exporter->run($host ?? '', $force);
        $output->writeln('Export took '.$duration.' ms.');

        return Command::SUCCESS;
    }
}
