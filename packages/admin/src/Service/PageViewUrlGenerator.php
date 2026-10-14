<?php

declare(strict_types=1);

namespace Pushword\Admin\Service;

use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Where the admin opens a page. A path stays on the admin's host and session,
 * so drafts show, but reaches the page's app only from one of that app's hosts
 * or from a gateway host (under /{host}/). From any other host the page opens
 * on its app's base_live_url — not base_url, which may be a static export
 * without drafts.
 */
final readonly class PageViewUrlGenerator
{
    public function __construct(
        private SiteRegistry $apps,
        private PushwordRouteGenerator $routeGenerator,
        private UrlGeneratorInterface $router,
    ) {
    }

    #[AsTwigFunction('pw_page_view_url')]
    public function generate(Page $page): string
    {
        $pageHost = '' !== $page->host ? $page->host : $this->apps->getDefault()->getMainHost();
        // The request's host, not the current site: the edit form and the list's
        // host filter switch that to the page's app.
        $adminHost = $this->apps->getCurrentHost() ?? '';
        $path = $this->router->generate(PushwordRouteGenerator::PATH, ['slug' => $page->getRealSlug()]);

        if ($this->apps->findHost($adminHost) === $pageHost) {
            return $path;
        }

        if (! $this->apps->isGatewayHost($adminHost)) {
            return rtrim($this->apps->get($pageHost)->getStr('base_live_url'), '/').$path;
        }

        return $this->apps->isDefaultHost($pageHost)
            ? $path
            : $this->routeGenerator->generate($page, host: $pageHost, forceUseCustomPath: true);
    }
}
