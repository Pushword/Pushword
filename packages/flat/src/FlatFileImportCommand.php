<?php

namespace Pushword\Flat;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @template T of object
 */
#[AsCommand(name: 'pushword:flat:import')]
class FlatFileImportCommand extends Command
{
    /**
     * @param FlatFileImporter<T> $importer
     */
    public function __construct(
        protected FlatFileImporter $importer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Syncing flat file inside database.')
            ->addArgument('host', InputArgument::OPTIONAL, '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Import will start in few seconds...');

        $this->importer->run($input->getArgument('host'));

        $output->writeln('Import ended.');

        return 0;
    }
}
