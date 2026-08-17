<?php

// namespace App\Command;

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'bulkContentEdit')]
final readonly class BulkEditExampleCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        private PageRepository $pageRepo,
        private Filesystem $fs,
        private string $databaseUrl = 'sqlite:///var/app.db',
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        if (! str_starts_with($this->databaseUrl, 'sqlite:')) {
            $output->writeln('<error>This example command requires the SQLite file backup it creates before editing.</error>');

            return Command::FAILURE;
        }

        if ($this->fs->exists('var/app.db')) {
            $backupFileName = 'var/app.db~'.date('YmdHis');
            $this->fs->copy('var/app.db', $backupFileName);
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
