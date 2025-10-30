<?php

namespace Pushword\Flat;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
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
        OutputInterface $output
    ): int {
        if ($this->flatFileSync->mustImport($host)) {
            $output->writeln('Import detected - running import...');
            $this->importer->run($host);
            $output->writeln('Import ended.');

            return Command::SUCCESS;
        }

        $output->writeln('Export detected - running export...');
        $this->exporter->run($host ?? '');
        $output->writeln('Export ended.');

        return Command::SUCCESS;
    }
}
