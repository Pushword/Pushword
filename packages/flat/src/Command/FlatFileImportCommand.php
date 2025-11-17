<?php

namespace Pushword\Flat\Command;

use Pushword\Flat\FlatFileImporter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @template T of object
 */
#[AsCommand(name: 'pw:flat:import', description: 'Syncing flat file inside database.')]
final class FlatFileImportCommand
{
    /**
     * @param FlatFileImporter<T> $importer
     */
    public function __construct(
        protected FlatFileImporter $importer,
        private readonly Filesystem $fs
    ) {
    }

    public function __invoke(
        #[Argument(name: 'host')]
        ?string $host,
        OutputInterface $output
    ): int {
        $backupFileName = 'var/app.db~'.date('YmdHis');
        $this->fs->copy('var/app.db', $backupFileName);

        $output->writeln('Import will start in few seconds...');

        $duration = $this->importer->run($host);

        $output->writeln('Import took '.$duration.' ms.');

        return Command::SUCCESS;
    }
}
