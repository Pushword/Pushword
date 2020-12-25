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
            ->addArgument('host', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getArgument('host')) {
            $this->staticAppGenerator->generateFromHost($input->getArgument('host'));
        } else {
            $this->staticAppGenerator->generateAll();
        }

        $output->writeln('Static version generation succeeded.');

        return 0;
    }
}
