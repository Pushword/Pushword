<?php

// namespace App\Command;

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\AdminBlockEditor\EditorJsHelper;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'bulkContentEdit')]
final class BulkEditExampleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PageRepository $pageRepo,
        private Filesystem $fs,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $backupFileName = 'var/app.db~'.date('YmdHis');
        $this->fs->copy('var/app.db', $backupFileName);
        $output->writeln('Backup created: '.$backupFileName);

        $pages = $this->pageRepo->findAll();
        foreach ($pages as $page) {
            if ($page->hasRedirection()) {
                continue;
            }

            EditorJsHelper::addAnchor($page, 'avis', '/\bAvis\b/i', ['header'], [$output, 'writeln']);
            // $page->setMainContent(str_replace('escape-game-gap', 'escape-game-champsaur-valgaudemar', $page->getMainContent()));
        }

        $this->em->flush();

        return 0;
    }
}
