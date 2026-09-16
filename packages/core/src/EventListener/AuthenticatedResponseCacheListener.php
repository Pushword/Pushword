<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;

/**
 * Keeps an authenticated response out of every cache — the browser back button,
 * a shared proxy, and the auto cache-control Symfony would otherwise apply.
 *
 * Stateless requests are skipped, as in {@see PwAuthCookieHealListener}.
 * {@see Security::getUser()} reads the token through UsageTrackingTokenStorage,
 * which increments the session usage index; {@see AbstractSessionListener} then
 * sees a used session on a request declared stateless and throws an
 * UnexpectedSessionUsageException (debug) or logs a warning (prod). Nothing is
 * lost: that auto cache-control is gated on the same usage index, so a stateless
 * response never had it to opt out of.
 */
final readonly class AuthenticatedResponseCacheListener
{
    public function __construct(private Security $security)
    {
    }

    #[AsEventListener(event: ResponseEvent::class, priority: -100)]
    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest() || $event->getRequest()->attributes->getBoolean('_stateless')) {
            return;
        }

        if (null === $this->security->getUser()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('Cache-Control', 'private, no-store');
        $headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
    }
}
