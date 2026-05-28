<?php

namespace Pushword\Core\Controller;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lightweight endpoint used by the front-end to know whether the visitor is
 * authenticated. Returns 204 when authenticated, 401 otherwise. Used by the
 * unpublished-link-restorer JS to put back <a> tags for editors without
 * needing per-user HTML cache invalidation.
 */
final readonly class AuthCheckController
{
    public function __construct(
        private Security $security,
    ) {
    }

    #[Route('/_pushword/auth-check', name: 'pushword_auth_check', methods: ['GET', 'HEAD'], priority: 100)]
    public function check(): Response
    {
        $authenticated = $this->security->isGranted('IS_AUTHENTICATED_FULLY');
        $response = new Response('', $authenticated ? Response::HTTP_NO_CONTENT : Response::HTTP_UNAUTHORIZED);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
