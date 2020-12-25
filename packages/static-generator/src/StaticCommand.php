<?php

namespace Pushword\StaticGenerator;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsCommand(name: 'pushword:static:generate')]
#[AutoconfigureTag('console.command')]
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
        $host = $input->getArgument('host');
        if (null === $host) {
            $this->staticAppGenerator->generate();
            $this->printStatus($output, 'All websites generated witch success.');

            return 0;
        }

        $page = $input->getArgument('page');
        if (null === $page) {
            $this->staticAppGenerator->generate($host);
            $this->printStatus($output, $host.' generated witch success.');

            return 0;
        }

        $this->staticAppGenerator->generatePage($host,  $page);
        $this->printStatus($output, $host."'s page generated witch success.");

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
