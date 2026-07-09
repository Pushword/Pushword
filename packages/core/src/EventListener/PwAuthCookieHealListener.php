<?php

namespace Pushword\Core\EventListener;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Heals the invariant "authenticated ⇒ pw_auth=1 cookie present" without forcing
 * a re-login.
 *
 * {@see PwAuthCookieListener} only writes pw_auth on LoginSuccessEvent, but the
 * authentication can outlive that write: pw_auth is a browser-session cookie while
 * remember-me keeps the user logged in across browser restarts, and sessions that
 * predate this feature never received it. The outcome is an authenticated user
 * without pw_auth, so the client-side liveBlock that loads the admin toolbar (see
 * page_default.html.twig, block admin_buttons) never fires.
 *
 * On every authenticated main request that arrives without the cookie, we re-set
 * it — using {@see Security::getUser()}, which also covers remember-me. Requests
 * that already carry pw_auth=1 are skipped, so at most one Set-Cookie is emitted
 * per browser session, never on every response. The header lands in document.cookie
 * before the page scripts run, so the toolbar reappears on the first authenticated
 * load, with no second reload.
 *
 * Stateless firewalls (the token-authenticated REST API) are skipped: the cookie
 * is a browser hint and API clients never replay it, so healing there would emit a
 * useless Set-Cookie on every single JSON response.
 *
 * Known limitation: on a fully static domain (HTML served by Caddy, PHP never
 * executed) this listener does not run, so those pages are not healed. Every
 * dynamic surface — the preview and any dynamic authenticated page — is covered,
 * which is what the reported bug needs.
 */
final readonly class PwAuthCookieHealListener
{
    public function __construct(
        private Security $security,
    ) {
    }

    #[AsEventListener(event: ResponseEvent::class)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('1' === $request->cookies->get(PwAuthCookieListener::COOKIE_NAME)) {
            return;
        }

        if ($request->attributes->getBoolean('_stateless')) {
            return;
        }

        if (null === $this->security->getUser()) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            PwAuthCookieListener::createAuthCookie($request->isSecure()),
        );
    }
}
