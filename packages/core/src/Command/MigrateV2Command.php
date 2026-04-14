<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pw:migrate',
    description: 'Migrate data: promote template column, fix searchExcerpt typo',
)]
final readonly class MigrateV2Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private PageRepository $pageRepo,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $pages = $this->pageRepo->findAll();
        $migrated = 0;

        foreach ($pages as $page) {
            $changed = false;

            // 1. Promote template from customProperties to real column
            if (null === $page->getTemplate() && $page->hasCustomProperty('template')) {
                $template = $page->getCustomProperty('template');
                if (\is_string($template)) {
                    $page->setTemplate($template);
                    $page->removeCustomProperty('template');
                    $changed = true;
                    $output->writeln('  Promoted template for page "'.$page->getSlug().'"');
                }
            }

            // 2. Rename searchExcrept -> searchExcerpt (fix typo)
            if ($page->hasCustomProperty('searchExcrept')) {
                $value = $page->getCustomProperty('searchExcrept');
                $page->removeCustomProperty('searchExcrept');
                $page->setCustomProperty('searchExcerpt', $value);
                $changed = true;
                $output->writeln('  Fixed searchExcerpt typo for page "'.$page->getSlug().'"');
            }

            if ($changed) {
                ++$migrated;
            }
        }

        $this->em->flush();

        $output->writeln(\sprintf('Migrated %d pages.', $migrated));

        return Command::SUCCESS;
    }
}
