<?php

// namespace App\Command;

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\SqliteBackupManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bulkContentEdit')]
final readonly class BulkEditExampleCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        private PageRepository $pageRepo,
        private SqliteBackupManager $backupManager,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        if (! $this->backupManager->isSupported()) {
            $output->writeln('<error>This example command requires the SQLite file backup it creates before editing.</error>');

            return Command::FAILURE;
        }

        if ($this->backupManager->databaseExists()) {
            $backupFileName = $this->backupManager->create();
            $output->writeln('Backup created: '.$backupFileName);
        }

        $pages = $this->pageRepo->findAll();
        foreach ($pages as $page) {
            if ($page->hasRedirection()) {
                continue;
            }

            // EditorJsHelper::addAnchor($page, 'avis', '/\bAvis\b/i', ['header'], [$output, 'writeln']);
            $this->updateSrcDataLive($output, $page);
        }

        $this->em->flush();

        return 0;
    }

    private function updateSrcDataLive(OutputInterface $output, Page $page): void
    {
        if (str_contains($page->mainContent, 'src-data-live')) {
            $output->writeln($page->host.'/'.$page->slug.' : update src-data-live');
            $page->mainContent = str_replace('src-data-live', 'data-src-live', $page->mainContent);
        }
    }
}
