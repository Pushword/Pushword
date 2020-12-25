<?php

namespace Pushword\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Admin\PageCheatSheetAdmin;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('controller.service_arguments')]
class PageCheatSheetController extends AbstractController
{
    public function __construct(
        private readonly PageRepository $pageRepo,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('admin/cheatsheet', methods: ['GET', 'HEAD', 'POST'], name: 'cheatsheetEditRoute')]
    public function cheatsheet(): Response
    {
        if (null === ($page = $this->pageRepo->findOneBy(['slug' => PageCheatSheetAdmin::CHEATSHEET_SLUG]))) {
            $page = (new Page());
            $page->setSlug(PageCheatSheetAdmin::CHEATSHEET_SLUG);
            $page->setH1($this->translator->trans('admin.label.cheatsheet'));
            $this->entityManager->persist($page);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('admin_cheatsheet_edit', ['id' => $page->getId()]);
    }
}
