<?php

namespace Pushword\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class PageCheatSheetController extends AbstractController
{
    public function __construct(
        private PageRepository $pageRepo,
        private ParameterBagInterface $parameterBag,
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('admin/cheatsheet', methods: ['GET', 'HEAD', 'POST'], name: 'cheatsheetEditRoute')]
    public function cheatsheet(): Response
    {
        if (null === ($page = $this->pageRepo->findOneBy(['slug' => PageCheatSheetAdmin::CHEATSHEET_SLUG]))) {
            $pageClass = $this->parameterBag->get('pw.entity_page');
            /** @var PageInterface $page */
            $page = (new $pageClass());
            $page->setSlug(PageCheatSheetAdmin::CHEATSHEET_SLUG);
            $page->setH1($this->translator->trans('admin.label.cheatsheet'));
            $this->entityManager->persist($page);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('admin_cheatsheet_edit', ['id' => $page->getId()]);
    }
}
