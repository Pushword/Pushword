<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Service\LinkCollectorService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 255)]
final readonly class LinkCollectorResetListener
{
    public function __construct(
        private LinkCollectorService $linkCollector,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $this->linkCollector->reset();
    }
}
