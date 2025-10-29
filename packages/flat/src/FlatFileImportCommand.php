<?php

namespace Pushword\Flat;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @template T of object
 */
#[AsCommand(name: 'pushword:flat:import', description: 'Syncing flat file inside database.')]
final class FlatFileImportCommand
{
    /**
     * @param FlatFileImporter<T> $importer
     */
    public function __construct(protected FlatFileImporter $importer)
    {
    }

    public function __invoke(#[Argument(name: 'host')]
        ?string $host, OutputInterface $output): int
    {
        $output->writeln('Import will start in few seconds...');

        $this->importer->run($host);

        $output->writeln('Import ended.');

        return Command::SUCCESS;
    }
}
