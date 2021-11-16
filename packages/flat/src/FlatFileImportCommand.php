<?php

namespace Pushword\Flat;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @template T of object
 */
class FlatFileImportCommand extends Command
{
    /**
     * @noRector
     */
    protected static $defaultName = 'pushword:flat:import';

    /**
     * @var FlatFileImporter<T>
     */
    protected \Pushword\Flat\FlatFileImporter $importer;

    /**
     * @param FlatFileImporter<T> $flatFileImporter
     */
    public function __construct(
        FlatFileImporter $flatFileImporter
    ) {
        $this->importer = $flatFileImporter;

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
