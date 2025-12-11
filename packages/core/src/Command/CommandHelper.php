<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\AdminBlockEditor\EditorJsHelper;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Pushword\Core\Entity\Page;

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

}