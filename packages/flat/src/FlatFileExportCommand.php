<?php

namespace Pushword\Flat;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FlatFileExportCommand extends Command
{
    protected FlatFileExporter $exporter;

    public function __construct(
        FlatFileExporter $exporter
    ) {
        $this->exporter = $exporter;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('pushword:flat:export')
            ->setDescription('Exporting database toward flat yaml files (and json for media).')
            ->addArgument('host', InputArgument::OPTIONAL, '')
            ->addArgument('exportDir', InputArgument::OPTIONAL, '');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Import will start in few seconds...');

        if ($input->getArgument('exportDir')) {
            $this->exporter->setExportDir($input->getArgument('exportDir'));
        }

        $exportDir = $this->exporter->run($input->getArgument('host'));

        if (! $input->getArgument('exportDir')) {
            $output->writeln('Results:');
            $output->writeln($exportDir);
        }
        $output->writeln('Import ended.');

        return 0;
    }
}
