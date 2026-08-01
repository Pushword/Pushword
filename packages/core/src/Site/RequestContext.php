<?php

namespace Pushword\Core\Site;

use LogicException;
use Pushword\Core\Entity\Page;
use Symfony\Contracts\Service\ResetInterface;

final class RequestContext implements ResetInterface
{
    public private(set) SiteConfig $currentSite;

    public ?Page $currentPage = null;

    public private(set) ?string $currentHost = null;

    public private(set) ?string $currentRoute = null;

    public private(set) ?string $currentSlug = null;

    public private(set) int $currentPager = 1;

    public function __construct(
        private readonly SiteRegistry $siteRegistry,
    ) {
        $this->currentSite = $siteRegistry->getDefault();
    }

    /**
     * Worker-mode safety (kernel.reset): wipe every request-scoped field so one
     * request's page/host/route/slug/pager never leaks into the next when a single
     * kernel serves many requests (and many hosts). Mirrors a fresh process: the
     * site falls back to the default, exactly as the constructor leaves it.
     */
    public function reset(): void
    {
        $this->currentSite = $this->siteRegistry->getDefault();
        $this->currentPage = null;
        $this->currentHost = null;
        $this->currentRoute = null;
        $this->currentSlug = null;
        $this->currentPager = 1;
    }

    public function switchSite(Page|string $host): self
    {
        if ('' === $host) {
            return $this;
        }

        if ($host instanceof Page) {
            $this->currentPage = $host;
            $host = $host->host;
        }

        $this->currentSite = $this->siteRegistry->get($host);

        return $this;
    }

    public function requirePage(): Page
    {
        return $this->currentPage ?? throw new LogicException('No current page set');
    }

    public function setRequestContext(string $host, string $route = '', string $slug = '', int $pager = 1): self
    {
        $this->currentHost = $host;
        $this->currentRoute = $route;
        $this->currentSlug = $slug;
        $this->currentPager = $pager;

        return $this;
    }

    public function getLocale(): string
    {
        if (null !== $this->currentPage && '' !== $this->currentPage->locale) {
            return $this->currentPage->locale;
        }

        return $this->currentSite->locale;
    }

    public function getMainHost(): string
    {
        return $this->currentSite->getMainHost();
    }

    public function sameHost(?string $host): bool
    {
        if (null === $host) {
            return $this->siteRegistry->isDefaultHost($this->currentSite->getMainHost());
        }

        return $host === $this->currentSite->getMainHost();
    }
}
