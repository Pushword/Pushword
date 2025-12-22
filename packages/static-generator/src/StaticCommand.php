<?php

namespace Pushword\StaticGenerator;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(name: 'pw:static', description: 'Generate static version for your website')]
#[AutoconfigureTag('console.command')]
final readonly class StaticCommand
{
    public function __construct(
        private StaticAppGenerator $staticAppGenerator,
        private Stopwatch $stopWatch,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Argument(name: 'page')]
        ?string $page,
        #[Option(name: 'incremental', shortcut: 'i', description: 'Only regenerate pages that have changed since last generation')]
        bool $incremental = false,
    ): int {
        $this->stopWatch->start('generate');

        if (null === $host) {
            $this->staticAppGenerator->generate(null, $incremental);
            $msg = 'All websites generated with success';
            if ($incremental) {
                $msg .= ' (incremental mode)';
            }
        } elseif (null === $page) {
            $this->staticAppGenerator->generate($host, $incremental);
            $msg = $host.' generated with success.';
            if ($incremental) {
                $msg .= ' (incremental mode)';
            }
        } else {
            $this->staticAppGenerator->generatePage($host, $page);
            $msg = $host.'/'.$page.' generated with success.';
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
