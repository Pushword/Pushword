<?php

declare(strict_types=1);

namespace Pushword\Admin\Controller;

use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Renders small HTML fragments that cached/static pages load client-side via
 * liveBlock (js-helper). Each endpoint is a plain controller behind the admin
 * firewall — the `pw_auth` cookie only gates the client-side fetch.
 */
#[IsGranted('ROLE_EDITOR')]
final class AdminFragmentController extends AbstractController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {
    }

    #[Route(
        path: '/admin/fragment/page-buttons/{id}',
        name: 'pushword_admin_fragment_page_buttons',
        methods: ['GET', 'POST'],
    )]
    public function pageButtons(int $id): Response
    {
        $page = $this->pageRepository->find($id);
        if (! $page instanceof Page) {
            throw new NotFoundHttpException();
        }

        return $this->render('@Pushword/page/_admin_buttons.html.twig', [
            'page' => $page,
        ]);
    }
}
