<?php

namespace Pushword\Core\Controller;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lightweight endpoint telling the front-end whether the visitor is
 * authenticated. Returns 204 when authenticated, 401 otherwise.
 *
 * Compatibility only. The unpublished-link restorer used to call this on every
 * page carrying a draft link; it now reads the editor-only `pw_auth` cookie
 * instead ({@see \Pushword\Core\EventListener\PwAuthCookieListener}), which costs
 * no request and no console error — the browser's network stack logs any 4xx
 * resource, and Lighthouse counts it against best-practices.
 *
 * The route stays because bundles built before that change are still deployed and
 * still probe it, and it must keep answering 401 to anonymous visitors for exactly
 * that reason: those bundles key off `response.ok`, so an always-200 answer would
 * make every anonymous visitor restore the very draft links this feature hides.
 * AuthCheckControllerTest guards that. Drop the route once no deployed bundle
 * probes it.
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

        // Reading the auth token marks the session as used (here, and again in
        // PwAuthCookieHealListener on the same response), which would otherwise let
        // Symfony's AbstractSessionListener prepend "max-age=0, must-revalidate,
        // private" to the Cache-Control set above. Opt out to keep it verbatim.
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $response;
    }
}
