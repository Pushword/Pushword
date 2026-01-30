<?php

namespace Pushword\Core\Site;

use LogicException;
use Pushword\Core\Entity\Page;

final class RequestContext
{
    private ?SiteConfig $currentSite = null;

    private ?Page $currentPage = null;

    private ?string $currentHost = null;

    private ?string $currentRoute = null;

    private ?string $currentSlug = null;

    private int $currentPager = 1;

    public function __construct(
        private readonly SiteRegistry $siteRegistry,
    ) {
        $this->currentSite = $siteRegistry->getDefault();
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

    public function setCurrentPage(Page $page): self
    {
        $this->currentPage = $page;

        return $this;
    }

    public function getCurrentPage(): ?Page
    {
        return $this->currentPage;
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

    public function getCurrentSite(): SiteConfig
    {
        return $this->currentSite ?? $this->siteRegistry->getDefault();
    }

    public function getCurrentHost(): ?string
    {
        return $this->currentHost;
    }

    public function getCurrentRoute(): ?string
    {
        return $this->currentRoute;
    }

    public function getCurrentPager(): int
    {
        return $this->currentPager;
    }

    public function getCurrentSlug(): ?string
    {
        return $this->currentSlug;
    }

    public function getLocale(): string
    {
        if (null !== $this->currentPage && '' !== $this->currentPage->locale) {
            return $this->currentPage->locale;
        }

        return $this->getCurrentSite()->getLocale();
    }

    public function getMainHost(): ?string
    {
        return $this->currentSite?->getMainHost();
    }

    public function sameHost(?string $host): bool
    {
        if (null === $host) {
            return $this->siteRegistry->isDefaultHost($this->currentSite?->getMainHost());
        }

        return $host === $this->currentSite?->getMainHost();
    }
}
