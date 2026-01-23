<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Component\App\AppPool;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
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
        $effectiveHost = $this->determineEffectiveHost($request);

        $this->apps->setRequestContext(
            host: $effectiveHost,
            route: $request->attributes->getString('_route', ''),
            slug: $request->attributes->getString('slug', ''),
            pager: $request->attributes->getInt('pager', 1)
        );

        if ('' !== $effectiveHost) {
            $this->apps->switchCurrentApp($effectiveHost);
        }
    }

    private function determineEffectiveHost(Request $request): string
    {
        $httpHost = $request->getHost();
        $routeHostAttr = $request->attributes->get('host');
        $routeHost = \is_string($routeHostAttr) ? $routeHostAttr : '';

        // Find the main host key for the HTTP host
        $knownHttpHost = $this->apps->findHost($httpHost);

        // If HTTP host is default or unknown, route {host} takes priority
        $httpHostIsDefaultOrUnknown = '' === $knownHttpHost
            || $this->apps->isDefaultHost($knownHttpHost);

        if ($httpHostIsDefaultOrUnknown && '' !== $routeHost) {
            $knownRouteHost = $this->apps->findHost($routeHost);
            if ('' !== $knownRouteHost) {
                return $knownRouteHost;
            }
        }

        // Otherwise use HTTP host (if known) or fall back to HTTP host string
        return '' !== $knownHttpHost ? $knownHttpHost : $httpHost;
    }
}
