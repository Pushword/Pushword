<?php

namespace Pushword\StaticGenerator;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StaticCommand extends Command
{
    private $staticAppGenerator;

    public function __construct(StaticAppGenerator $staticAppGenerator)
    {
        parent::__construct();
        $this->staticAppGenerator = $staticAppGenerator;
    }

    protected function configure()
    {
        $this
            ->setName('pushword:static:generate')
            ->setDescription('Generate static version  for your website')
            ->addArgument('host', InputArgument::OPTIONAL)
            ->addArgument('page', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! $input->getArgument('host')) {
            $this->staticAppGenerator->generateAll();
            $output->writeln('All websites generated witch success.');

            return 0;
        }

        if (! $input->getArgument('page')) {
            $this->staticAppGenerator->generateFromHost($input->getArgument('host'));
            $output->writeln($input->getArgument('host').' generated witch success.');

            return 0;
        }

        $this->staticAppGenerator->generatePage($input->getArgument('host'), $input->getArgument('page'));

        return 0;
    }
}
