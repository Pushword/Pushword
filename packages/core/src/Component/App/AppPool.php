<?php

namespace Pushword\Core\Component\App;

use Exception;
use LogicException;
use Pushword\Core\Entity\Page;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Twig\Environment as Twig;

final class AppPool
{
    /** @var array<string, AppConfig> */
    private array $apps = [];

    private ?string $currentApp = null;

    /**
     * Why there ? Because often, need to check current page don't override App Config. */
    private ?Page $currentPage = null;

    private ?string $currentHost = null;

    private ?string $currentRoute = null;

    private int $currentPager = 1;

    private ?string $currentSlug = null;

    /** @param array<string, array<string, mixed>> $rawApps */
    public function __construct(
        array $rawApps,
        Twig $twig,
        ParameterBagInterface $parameterBag,
        CacheInterface $cache
    ) {
        $firstHost = (string) array_key_first($rawApps);

        $firstApp = null;
        foreach ($rawApps as $mainHost => $app) {
            $this->apps[$mainHost] = new AppConfig($parameterBag, $app, $firstHost === $mainHost);
            $this->apps[$mainHost]->setTwig($twig);
            $this->apps[$mainHost]->setCache($cache);
            if (null === $firstApp) {
                $firstApp = $this->apps[$mainHost];
            }

            $this->apps[$mainHost]->firstAppLocale = $firstApp->getLocale();
        }

        $this->switchCurrentApp($firstHost);
    }

    public function setCurrentPage(Page $page): self
    {
        $this->currentPage = $page;

        return $this;
    }

    public function switchCurrentApp(Page|string $host): self
    {
        if ('' === $host) {
            return $this;
        }

        if ($host instanceof Page) {
            $this->currentPage = $host;
            $host = $host->host;
        }

        $app = $this->get($host);

        $this->currentApp = $app->getMainHost();

        return $this;
    }

    public function get(?string $host = ''): AppConfig
    {
        $host = \in_array($host, [null, ''], true) ? $this->currentApp : $host;
        if (null !== $host && isset($this->apps[$host])) {
            return $this->apps[$host];
        }

        $apps = array_reverse($this->apps, true);
        foreach ($apps as $app) {
            if (\in_array($host, $app->getHosts(), true)) {
                return $app;
            }
        }

        if (! isset($app)) {
            throw new Exception('No AppConfig found (`'.($host ?? '').'`)');
        }

        /** @var AppConfig $app */
        return $app;
    }

    /** @return string[] */
    public function getHosts(): array
    {
        return array_keys($this->apps);
    }

    public function findHost(string $host): string
    {
        foreach ($this->apps as $key => $app) {
            if (\in_array($host, $app->getHosts(), true)) {
                return $key;
            }
        }

        return '';
    }

    public function isKnownHost(string $host): bool
    {
        return '' !== $this->findHost($host);
    }

    /** @return array<string, AppConfig> */
    public function getApps(): array
    {
        return $this->apps;
    }

    public function getCurrentPage(): ?Page
    {
        return $this->currentPage;
    }

    public function safegetCurrentPage(): Page
    {
        if (null === $this->currentPage) {
            throw new LogicException();
        }

        return $this->currentPage;
    }

    public function setRequestContext(string $host, string $route = '', string $slug = '', int $pager = 1): self
    {
        $this->currentHost = $host;
        $this->currentRoute = $route;
        $this->currentSlug = $slug;
        $this->currentPager = $pager;

        return $this;
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

    /** @param string|array<string>|null $host */
    public function isDefaultHost(string|array|null $host = null): bool
    {
        $firstApp = array_key_first($this->apps);

        if (null === $host) {
            return $firstApp === $this->get()->getMainHost();
        }

        if (\is_string($host)) {
            return $firstApp === $host;
        }

        return \in_array($firstApp, $host, true);
    }

    /**
     * Alias for ->get()->getMainHost().
     */
    public function getMainHost(): ?string
    {
        return $this->currentApp;
    }

    public function sameHost(?string $host): bool
    {
        if (! $this->isDefaultHost()) {
            return $host === $this->currentApp;
        }

        if (null !== $host) {
            return $host === $this->currentApp;
        }

        return true;
    }

    public function getApp(string $host = ''): AppConfig
    {
        if ('' === $host) {
            $host = $this->currentApp;
        }

        return $this->get($host);
    }

    public function getAppValue(string $key, string $host = ''): mixed
    {
        return $this->getApp($host)->get($key);
    }

    /**
     * Returns the current locale, prioritizing page locale over app locale.
     */
    public function getLocale(): string
    {
        $currentPageLocale = null !== $this->currentPage ? $this->currentPage->locale : '';

        return '' !== $currentPageLocale
          ? $currentPageLocale
          : $this->get()->getLocale();
    }
}
