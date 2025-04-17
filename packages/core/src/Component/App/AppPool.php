<?php

namespace Pushword\Core\Component\App;

use Exception;
use LogicException;
use Pushword\Core\Entity\Page;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment as Twig;

final class AppPool
{
    /** @var array<string, AppConfig> */
    private array $apps = [];

    private ?string $currentApp = null;

    /**
     * Why there ? Because often, need to check current page don't override App Config. */
    private ?Page $currentPage = null;

    /** @param array<string, array<string, mixed>> $rawApps */
    public function __construct(array $rawApps, Twig $twig, ParameterBagInterface $parameterBag)
    {
        $firstHost = (string) array_key_first($rawApps);

        $firstApp = null;
        foreach ($rawApps as $mainHost => $app) {
            $this->apps[$mainHost] = new AppConfig($parameterBag, $app, $firstHost === $mainHost);
            $this->apps[$mainHost]->setTwig($twig);
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
        if ($host instanceof Page) {
            $this->currentPage = $host;
            $host = $host->getHost();
        }

        $app = $this->get($host);

        $this->currentApp = $app->getMainHost();

        return $this;
    }

    public function get(?string $host = ''): AppConfig
    {
        $host = \in_array($host, [null, ''], true) ? $this->currentApp : $host;
        if (isset($this->apps[$host])) {
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

    /** @param string|array<string>|null $host */
    public function isFirstApp(string|array|null $host = null): bool
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
        if (! $this->isFirstApp()) {
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
}
