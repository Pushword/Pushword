<?php

namespace Pushword\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Admin\Service\AdminUrlGeneratorAlias;
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
        private readonly AdminUrlGeneratorAlias $adminUrlGenerator,
    ) {
    }

    #[Route('admin/cheatsheet', methods: ['GET', 'HEAD', 'POST'], name: 'cheatsheetEditRoute')]
    public function cheatsheet(): Response
    {
        if (null === ($page = $this->pageRepo->findOneBy(['slug' => PageCheatSheetCrudController::CHEATSHEET_SLUG]))) {
            $page = (new Page());
            $page->setSlug(PageCheatSheetCrudController::CHEATSHEET_SLUG);
            $page->setH1($this->translator->trans('admin.label.cheatsheet'));
            $page->setMetaRobots('noindex');
            $this->entityManager->persist($page);
            $this->entityManager->flush();
        }

        return $this->redirect($this->adminUrlGenerator->generate('admin_cheatsheet_edit', [
            'id' => $page->getId(),
        ]));
    }
}
