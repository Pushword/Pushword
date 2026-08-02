<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pw:page:clean')]
final readonly class CleanPageCommand
{
    public function __construct(private EntityManagerInterface $em, private PageRepository $pageRepo)
    {
    }

    public function __invoke(): int
    {
        foreach ($this->pageRepo->findAll() as $page) {
            $page->mainContent = Page::normalizeMainContent($page->mainContent);
        }

        $this->em->flush();

        return 0;
    }
}
