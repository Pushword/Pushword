<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class RequestContextListener
{
    public function __construct(
        private SiteRegistry $siteRegistry,
        private RequestContext $requestContext,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $effectiveHost = $this->determineEffectiveHost($request);

        $this->requestContext->setRequestContext(
            host: $effectiveHost,
            route: $request->attributes->getString('_route', ''),
            slug: $request->attributes->getString('slug', ''),
            pager: $request->attributes->getInt('pager', 1),
        );

        if ('' !== $effectiveHost) {
            $this->requestContext->switchSite($effectiveHost);
        }
    }

    private function determineEffectiveHost(Request $request): string
    {
        $httpHost = $request->getHost();
        $routeHostAttr = $request->attributes->get('host');
        $routeHost = \is_string($routeHostAttr) ? $routeHostAttr : '';

        $knownHttpHost = $this->siteRegistry->findHost($httpHost);

        $httpHostIsDefaultOrUnknown = '' === $knownHttpHost
            || $this->siteRegistry->isDefaultHost($knownHttpHost);

        if ($httpHostIsDefaultOrUnknown && '' !== $routeHost) {
            $knownRouteHost = $this->siteRegistry->findHost($routeHost);
            if ('' !== $knownRouteHost) {
                return $knownRouteHost;
            }
        }

        return '' !== $knownHttpHost ? $knownHttpHost : $httpHost;
    }
}
