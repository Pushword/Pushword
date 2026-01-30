<?php

namespace Pushword\Core\Site;

use Deprecated;
use Exception;
use LogicException;
use Pushword\Core\Entity\Page;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class SiteRegistry
{
    /** @var array<string, SiteConfig> */
    private array $sites = [];

    private ?string $defaultHost = null;

    private ?RequestContext $requestContext = null;

    /** @param array<string, array<string, mixed>> $rawApps */
    public function __construct(
        array $rawApps,
        TemplateResolver $templateResolver,
        ParameterBagInterface $parameterBag,
    ) {
        $this->defaultHost = (string) array_key_first($rawApps);
        $firstLocale = null;

        foreach ($rawApps as $mainHost => $app) {
            $site = new SiteConfig($parameterBag, $app, $this->defaultHost === $mainHost);
            $site->setTemplateResolver($templateResolver);
            $firstLocale ??= $site->getLocale();
            $site->firstAppLocale = $firstLocale;
            $this->sites[$mainHost] = $site;
        }
    }

    public function setRequestContext(RequestContext $requestContext): void
    {
        $this->requestContext = $requestContext;
    }

    private function context(): RequestContext
    {
        return $this->requestContext ?? throw new LogicException('RequestContext not set on SiteRegistry');
    }

    // --- Pure registry methods ---

    public function get(?string $host = null): SiteConfig
    {
        if (null !== $host && '' !== $host) {
            return $this->findByHost($host) ?? $this->getDefault();
        }

        if (null !== $this->requestContext) {
            return $this->requestContext->getCurrentSite();
        }

        return $this->getDefault();
    }

    public function getDefault(): SiteConfig
    {
        if (null !== $this->defaultHost && isset($this->sites[$this->defaultHost])) {
            return $this->sites[$this->defaultHost];
        }

        throw new Exception('No sites configured');
    }

    public function findByHost(string $host): ?SiteConfig
    {
        if (isset($this->sites[$host])) {
            return $this->sites[$host];
        }

        foreach ($this->sites as $site) {
            if (\in_array($host, $site->getHosts(), true)) {
                return $site;
            }
        }

        return null;
    }

    public function findHost(string $host): string
    {
        foreach ($this->sites as $key => $site) {
            if (\in_array($host, $site->getHosts(), true)) {
                return $key;
            }
        }

        return '';
    }

    public function isKnownHost(string $host): bool
    {
        return '' !== $this->findHost($host);
    }

    /** @return string[] */
    public function getHosts(): array
    {
        return array_keys($this->sites);
    }

    /** @return array<string, SiteConfig> */
    public function getAll(): array
    {
        return $this->sites;
    }

    /** @param string|array<string>|null $host */
    public function isDefaultHost(string|array|null $host = null): bool
    {
        if (null === $host) {
            if (null !== $this->requestContext) {
                return $this->defaultHost === $this->requestContext->getMainHost();
            }

            return true;
        }

        if (\is_string($host)) {
            return $this->defaultHost === $host;
        }

        return \in_array($this->defaultHost, $host, true);
    }

    // --- Delegate to RequestContext for request-scoped state ---

    public function getApp(string $host = ''): SiteConfig
    {
        return $this->get('' === $host ? null : $host);
    }

    public function getAppValue(string $key, string $host = ''): mixed
    {
        return $this->getApp($host)->get($key);
    }

    public function switchSite(Page|string $host): self
    {
        $this->context()->switchSite($host);

        return $this;
    }

    public function setCurrentPage(Page $page): self
    {
        $this->context()->setCurrentPage($page);

        return $this;
    }

    public function getCurrentPage(): ?Page
    {
        return $this->context()->getCurrentPage();
    }

    public function requirePage(): Page
    {
        return $this->context()->requirePage();
    }

    #[Deprecated(message: 'Use RequestContext::setRequestContext() directly')]
    public function setRequestContextData(string $host, string $route = '', string $slug = '', int $pager = 1): self
    {
        $this->context()->setRequestContext($host, $route, $slug, $pager);

        return $this;
    }

    public function getCurrentHost(): ?string
    {
        return $this->context()->getCurrentHost();
    }

    public function getCurrentRoute(): ?string
    {
        return $this->context()->getCurrentRoute();
    }

    public function getCurrentPager(): int
    {
        return $this->context()->getCurrentPager();
    }

    public function getCurrentSlug(): ?string
    {
        return $this->context()->getCurrentSlug();
    }

    public function getMainHost(): ?string
    {
        return $this->context()->getMainHost();
    }

    public function getLocale(): string
    {
        return $this->context()->getLocale();
    }

    public function sameHost(?string $host): bool
    {
        return $this->context()->sameHost($host);
    }
}
