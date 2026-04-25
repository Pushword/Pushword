<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Sets / clears a `pw_auth=1` cookie on login/logout.
 *
 * The cookie is a client-side hint only — JS reads it to decide whether to request
 * dynamic admin fragments (e.g. admin buttons via liveBlock's data-live-if). It is
 * never trusted server-side; admin endpoints stay behind the Symfony firewall.
 *
 * Not HttpOnly by design: JavaScript has to read it. SameSite=Lax is fine because
 * this is a boolean presence check, not a session token.
 */
final readonly class PwAuthCookieListener
{
    public const string COOKIE_NAME = 'pw_auth';

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $response = $event->getResponse();
        if (! $response instanceof Response) {
            return;
        }

        $response->headers->setCookie(
            Cookie::create(self::COOKIE_NAME, '1')
                ->withPath('/')
                ->withSameSite(Cookie::SAMESITE_LAX)
                ->withHttpOnly(false)
                ->withSecure($event->getRequest()->isSecure()),
        );
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $response = $event->getResponse();
        if (! $response instanceof Response) {
            return;
        }

        $response->headers->clearCookie(self::COOKIE_NAME, '/');
    }
}
