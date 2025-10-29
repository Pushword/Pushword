<?php

namespace Pushword\StaticGenerator;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsCommand(name: 'pushword:static:generate', description: 'Generate static version for your website')]
#[AutoconfigureTag('console.command')]
final readonly class StaticCommand
{
    public function __construct(private StaticAppGenerator $staticAppGenerator)
    {
    }

    public function __invoke(#[Argument(name: 'host')]
        ?string $host, #[Argument(name: 'page')]
        ?string $page, OutputInterface $output): int
    {
        $host = $host;
        if (null === $host) {
            $this->staticAppGenerator->generate();
            $this->printStatus($output, 'All websites generated witch success.');

            return Command::SUCCESS;
        }

        $page = $page;
        if (null === $page) {
            $this->staticAppGenerator->generate($host);
            $this->printStatus($output, $host.' generated witch success.');

            return Command::SUCCESS;
        }

        $this->staticAppGenerator->generatePage($host, $page);
        $this->printStatus($output, $host."'s page generated witch success.");

        return Command::SUCCESS;
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
