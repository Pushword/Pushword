<?php

namespace Pushword\StaticGenerator;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(name: 'pw:static:generate', description: 'Generate static version for your website')]
#[AutoconfigureTag('console.command')]
final readonly class StaticCommand
{
    public function __construct(
        private StaticAppGenerator $staticAppGenerator,
        private Stopwatch $stopWatch
    ) {
    }

    public function __invoke(
        #[Argument(name: 'host')]
        ?string $host,
        #[Argument(name: 'page')]
        ?string $page,
        OutputInterface $output
    ): int {
        $this->stopWatch->start('generate');

        $host = $host;
        $page = $page;
        if (null === $host) {
            $this->staticAppGenerator->generate();
            $msg = 'All websites generated witch success';
        } elseif (null === $page) {
            $this->staticAppGenerator->generate($host);
            $msg = ($host.' generated witch success.');
        } else {
            $this->staticAppGenerator->generatePage($host, $page);
            $msg = ($host.'/'.$page.' generated witch success.');
        }

        $duration = $this->stopWatch->stop('generate')->getDuration();
        $this->printStatus($output, $msg.' ('.$duration.'ms).');

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
