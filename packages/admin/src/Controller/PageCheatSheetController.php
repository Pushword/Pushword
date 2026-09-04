<?php

namespace Pushword\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Admin\Service\AdminUrlGeneratorAlias;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('admin/cheatsheet', name: 'cheatsheetEditRoute', methods: ['GET', 'HEAD', 'POST'])]
    public function cheatsheet(Request $request): Response
    {
        $page = $this->pageRepo->findOneBy(['slug' => PageCheatSheetCrudController::CHEATSHEET_SLUG]);
        if (null !== $page) {
            return $this->redirectToEdit($page);
        }

        if (! $request->isMethod('POST')) {
            return $this->render('@pwAdmin/cheatsheet_create.html.twig');
        }

        if (! $this->isCsrfTokenValid('create_cheatsheet', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $page = new Page();
        $page->slug = PageCheatSheetCrudController::CHEATSHEET_SLUG;
        $page->h1 = $this->translator->trans('adminLabelCheatsheet');
        $page->metaRobots = 'noindex';

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $this->redirectToEdit($page);
    }

    private function redirectToEdit(Page $page): Response
    {
        return $this->redirect($this->adminUrlGenerator->generate('admin_cheatsheet_edit', [
            'id' => $page->id,
        ]));
    }
}
