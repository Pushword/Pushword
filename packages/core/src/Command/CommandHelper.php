<?php

namespace Pushword\Core\Command;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

final class CommandHelper
{
    public function __construct(
        private Filesystem $fs,
    ) {
    }

    public function createBackup(OutputInterface $output): void
    {
        $backupFileName = 'var/app.db~'.date('YmdHis');
        $this->fs->copy('var/app.db', $backupFileName);
        $output->writeln('Backup created: '.$backupFileName);
    }


    public function restoreLastBackup( OutputInterface $output): void
    {
        // find last backup file in var/app.db~* and restore it
        // tranform this in a command class with this method as invokable (the other one - create backup - could be used in other command)
    }
}
