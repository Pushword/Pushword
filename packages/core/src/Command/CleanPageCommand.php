<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CleanPageCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'pushword:page:clean';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PageRepository $pageRepo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pages = $this->pageRepo->findAll();
        foreach ($pages as $page) {
            $page->setMainContent($page->getMainContent());
        }

        $this->em->flush();

        return 0;
    }
}
