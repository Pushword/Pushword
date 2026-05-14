<?php

namespace Pushword\Admin\Controller;

use Pushword\Core\Entity\Page;
use Pushword\Core\EventListener\PwAuthCookieListener;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Renders small HTML fragments that cached/static pages load client-side via
 * liveBlock (js-helper). Each endpoint is a plain controller behind the admin
 * firewall — the `pw_auth` cookie only gates the client-side fetch.
 *
 * Auth is checked manually (not via #[IsGranted]) so unauthenticated requests
 * return 401 + cleared `pw_auth` cookie instead of being redirected to /login
 * by the firewall entry point — fetch() would otherwise follow the redirect
 * and swap the login HTML into the placeholder.
 */
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
        if (null === $this->getUser()) {
            return $this->emptyAndClearAuthCookie();
        }
        if (! $this->isGranted('ROLE_EDITOR')) {
            throw new AccessDeniedHttpException();
        }

        $page = $this->pageRepository->find($id);
        if (! $page instanceof Page) {
            throw new NotFoundHttpException();
        }

        return $this->render('@Pushword/page/_admin_buttons.html.twig', [
            'page' => $page,
        ]);
    }

    private function emptyAndClearAuthCookie(): Response
    {
        $response = new Response('', Response::HTTP_UNAUTHORIZED);
        $response->headers->clearCookie(PwAuthCookieListener::COOKIE_NAME, '/');

        return $response;
    }
}
