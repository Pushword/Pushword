<?php

namespace Pushword\StaticGenerator;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pushword:static:generate')]
class StaticCommand extends Command
{
    public function __construct(private readonly StaticAppGenerator $staticAppGenerator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Generate static version  for your website')
            ->addArgument('host', InputArgument::OPTIONAL)
            ->addArgument('page', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $input->getArgument('host')) {
            $this->staticAppGenerator->generate();
            $this->printStatus($output, 'All websites generated witch success.');

            return 0;
        }

        if (null === $input->getArgument('page')) {
            $this->staticAppGenerator->generate($input->getArgument('host'));
            $this->printStatus($output, $input->getArgument('host').' generated witch success.');

            return 0;
        }

        $this->staticAppGenerator->generatePage($input->getArgument('host'),  $input->getArgument('page'));
        $this->printStatus($output, $input->getArgument('host')."'s page generated witch success.");

        return 0;
    }

    private function printStatus(OutputInterface $output, string $successMessage): void
    {
        if ([] !== $this->staticAppGenerator->getErrors()) {
            foreach ($this->staticAppGenerator->getErrors() as $error) {
                $output->writeln($error);
            }

            return;
        }

        $output->writeln($successMessage);
    }
}
