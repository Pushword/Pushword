<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Pushword\Core\Image\ImageCachePolicy;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/** Public image bytes must stay cacheable even on their first, generated response. */
final readonly class OnDemandImageCacheListener
{
    public function __construct(private ImageCachePolicy $imageCachePolicy)
    {
    }

    // After routing (32), before the lazy firewall (8) and response auth hooks.
    #[AsEventListener(event: RequestEvent::class, priority: 16)]
    public function __invoke(RequestEvent $event): void
    {
        if ($event->isMainRequest() && $this->imageCachePolicy->isOnDemand() && 'pushword_media_cache' === $event->getRequest()->attributes->get('_route')) {
            $event->getRequest()->attributes->set('_stateless', true);
        }
    }
}
