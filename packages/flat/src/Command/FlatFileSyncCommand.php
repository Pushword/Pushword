<?php

namespace Pushword\Flat\Command;

use Pushword\Flat\FlatFileSync;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pw:flat:sync',
    description: 'Syncing database toward flat yaml files (and json for media).'
)]
final readonly class FlatFileSyncCommand
{
    public function __construct(
        private FlatFileSync $flatFileSync,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Option(name: 'force', shortcut: 'f')]
        bool $force = false,
    ): int {
        $this->flatFileSync->sync($host, $force);

        $output->writeln('Sync completed.');

        return Command::SUCCESS;
    }
}
