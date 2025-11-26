<?php

namespace Pushword\Flat\Command;

use Pushword\Flat\FlatFileSync;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'pw:flat:import', description: 'Syncing flat file inside database.')]
final class FlatFileImportCommand
{
    public function __construct(
        protected FlatFileSync $flatFileSync,
        private readonly Filesystem $fs
    ) {
    }

    public function __invoke(
        #[Argument(name: 'host')]
        ?string $host,
        OutputInterface $output
    ): int {
        if ($this->fs->exists('var/app.db')) {
            $backupFileName = 'var/app.db~'.date('YmdHis');
            $this->fs->copy('var/app.db', $backupFileName);
        }

        $output->writeln('Import will start in few seconds...');

        $this->flatFileSync->import($host);

        $output->writeln('Import completed.');

        return Command::SUCCESS;
    }
}
