<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Component\App\AppPool;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class RequestContextListener
{
    public function __construct(
        private AppPool $apps,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $this->apps->setRequestContext(
            host: $request->getHost(),
            route: $request->attributes->getString('_route', ''),
            slug: $request->attributes->getString('slug', ''),
            pager: $request->attributes->getInt('pager', 1)
        );

        // Switch current app based on host attribute or request host
        $hostAttr = $request->attributes->get('host');
        $host = \is_string($hostAttr) ? $hostAttr : '';
        if ('' === $host) {
            $host = $this->apps->findHost($request->getHost());
        }

        if ('' !== $host) {
            $this->apps->switchCurrentApp($host);
        }
    }
}
