<?php

namespace Pushword\Core\EventListener;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;

final readonly class AuthenticatedResponseCacheListener
{
    public function __construct(private Security $security)
    {
    }

    #[AsEventListener(event: ResponseEvent::class, priority: -100)]
    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest() || null === $this->security->getUser()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('Cache-Control', 'private, no-store');
        $headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
    }
}
