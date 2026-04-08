<?php

declare(strict_types=1);

namespace Pushword\Core\Command;

use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Utils\LastTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pw:cron', description: 'Check for newly published scheduled pages and run configured commands')]
final readonly class CronCommand
{
    /**
     * @param array<array{command: string, on: string}> $scheduledCommands
     */
    public function __construct(
        private PageRepository $pageRepo,
        private BackgroundTaskDispatcherInterface $dispatcher,
        private string $varDir,
        private array $scheduledCommands,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $publishCommands = array_filter(
            $this->scheduledCommands,
            static fn (array $entry): bool => 'publish' === $entry['on'],
        );

        if ([] === $publishCommands) {
            $output->writeln('No on:publish commands configured.');

            return Command::SUCCESS;
        }

        $lastTime = new LastTime($this->varDir.'/pw-cron-last-run');
        $lastRun = $lastTime->get();

        if (null === $lastRun) {
            $lastTime->set();
            $output->writeln('First run — initialized timestamp.');

            return Command::SUCCESS;
        }

        $newlyPublished = $this->pageRepo->findNewlyPublishedSince($lastRun);
        $lastTime->set();

        if ([] === $newlyPublished) {
            return Command::SUCCESS;
        }

        $output->writeln(\count($newlyPublished).' page(s) became published.');

        foreach ($publishCommands as $entry) {
            $commandParts = ['php', 'bin/console', ...explode(' ', $entry['command'])];
            $this->dispatcher->dispatch('cron-publish', $commandParts, $entry['command']);
            $output->writeln('Dispatched: '.$entry['command']);
        }

        return Command::SUCCESS;
    }
}
