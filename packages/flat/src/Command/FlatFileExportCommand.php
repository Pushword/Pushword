<?php

namespace Pushword\Flat\Command;

use Pushword\Flat\FlatFileExporter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pw:flat:export',
    description: 'Exporting database toward flat yaml files (and json for media).'
)]
final readonly class FlatFileExportCommand
{
    public function __construct(
        private FlatFileExporter $exporter,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Argument(name: 'exportDir')]
        ?string $exportDir,
        #[Option(name: 'force', shortcut: 'f')]
        bool $force = false,
    ): int {
        $output->writeln('Export will start in few seconds...');

        if (null !== $exportDir && '' !== $exportDir) {
            $this->exporter->exportDir = $exportDir;
        }

        $duration = $this->exporter->run($host ?? '', $force);

        if ('' !== $this->exporter->exportDir) {
            $output->writeln('Results:');
            $output->writeln($this->exporter->exportDir);
        }

        $output->writeln('Export took '.$duration.' ms.');

        return Command::SUCCESS;
    }
}
