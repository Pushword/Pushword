<?php

namespace Pushword\Core\Router;

use Exception;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Twig\Attribute\AsTwigFunction;

final readonly class RouterTwigExtension
{
    public function __construct(
        private PushwordRouteGenerator $router,
        private PageRepository $pageRepository,
        private SiteRegistry $siteRegistry,
    ) {
    }

    #[AsTwigFunction('page')]
    public function getPageUri(mixed ...$args): string
    {
        $slug = $args[0] ?? throw new Exception('must use a string or page object');
        if (! \is_string($slug) && ! $slug instanceof Page) {
            throw new Exception('`page()` first argument must be a string or a Page Object');
        }

        if ($slug instanceof Page) {
            $host = $slug->host;
        }

        $arg2 = $args[1] ?? null;
        if (\is_string($arg2)) {
            $host = $arg2;
        } elseif (\is_int($arg2)) {
            $pager = $arg2;
        }

        $canonical = \is_bool($arg2) ? $arg2 : false;

        $arg3 = $args[2] ?? null;
        if (! isset($host) && \is_string($arg3)) {
            $host = $arg3;
        }

        $pager ??= \is_int($arg3) ? $arg3 : null;

        $arg4 = $args[3] ?? null;
        $host ??= \is_string($arg4) ? $arg4 : null;

        // Resolve host from the current site when not explicitly provided so the
        // repository can warm its lightweight URI cache. Without this, the
        // empty-host sentinel would short-circuit the redirect lookups below.
        $host ??= $this->siteRegistry->getMainHost() ?? '';

        // Page instance: everything is already hydrated, no DB access required.
        if ($slug instanceof Page) {
            if ($slug->hasRedirection()) {
                return $this->resolveRedirection($slug->getRedirectionUrl(), $host, $canonical, $pager);
            }

            return $this->router->generate($slug, $canonical, $pager, $host);
        }

        // String slug: only the redirect map is consulted on the hot path.
        $redirect = $this->pageRepository->getRedirectFor($slug, $host);
        if (null !== $redirect) {
            return $this->resolveRedirection($redirect['url'], $host, $canonical, $pager);
        }

        return $this->router->generate($slug, $canonical, $pager, $host);
    }

    private function resolveRedirection(string $redirectionUrl, string $host, bool $canonical, ?int $pager): string
    {
        if (str_starts_with($redirectionUrl, '/')) {
            $targetSlug = ltrim($redirectionUrl, '/');
            if ($this->pageRepository->hasSlug($targetSlug, $host)) {
                return $this->router->generate($targetSlug, $canonical, $pager, $host);
            }
        }

        return $redirectionUrl;
    }

    #[AsTwigFunction('is_current_page')]
    public function isCurrentPage(string $uri, ?Page $currentPage): bool
    {
        return
            null === $currentPage || $uri !== $this->router->generate($currentPage->getRealSlug())
            ? false
            : true;
    }
}
