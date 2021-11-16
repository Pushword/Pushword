<?php

namespace Pushword\Core\Router;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\PageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface as SfRouterInterface;

final class Router implements RouterInterface
{
    private SfRouterInterface $router;

    private bool $useCustomHostPath = true;

    private \Pushword\Core\Component\App\AppPool $apps;

    private string $currentHost;

    public function __construct(
        SfRouterInterface $sfRouter,
        AppPool $appPool,
        RequestStack $requestStack
    ) {
        $this->router = $sfRouter;
        $this->apps = $appPool;
        $this->currentHost = null !== $requestStack->getCurrentRequest() ? $requestStack->getCurrentRequest()->getHost() : '';
    }

    /**
     * This function assume you are usin /X for X pages's home
     * and / for YY page home if your default language is YY
     * X/Y may be en/fr/...
     */
    public function generatePathForHomePage(?PageInterface $page = null, bool $canonical = false): string
    {
        $homepage = (new Page())->setSlug('');

        if (null !== $page) {
            if ($page->getLocale() !== $this->apps->get()->getDefaultLocale()) {
                $homepage->setSlug($page->getLocale());
            }

            $homepage->setHost($page->getHost());
        } else {
            $homepage->setLocale($this->apps->get()->getLocale())->setHost($this->apps->get()->getMainHost());
        }

        return $this->generate($homepage, $canonical);
    }

    /**
     * @param string|PageInterface $slug
     * @param int|string|null      $pager
     */
    public function generate($slug = 'homepage', bool $canonical = false, $pager = null): string
    {
        $page = null;

        if ($slug instanceof PageInterface) {
            $page = $slug;
            $slug = $slug->getRealSlug();
        } elseif ('homepage' == $slug) {
            $slug = '';
        }

        if (! $canonical) {
            if ($this->mayUseCustomPath()) {
                return $this->router->generate(self::CUSTOM_HOST_PATH, [
                    'host' => $this->apps->safegetCurrentPage()->getHost(),
                    'slug' => $slug,
                ]);
            } elseif (null !== $page && ! $this->apps->sameHost($page->getHost())) { // maybe we force canonical - useful for views
                $canonical = true;
            }
        }

        if ($canonical && null !== $page) {
            $baseUrl = $this->apps->getAppValue('baseUrl', $page->getHost());
        }

        $url = ($baseUrl ?? '').$this->router->generate(self::PATH, ['slug' => $slug]);

        if (null !== $pager && '1' !== (string) $pager) {
            $url = rtrim($url, '/').'/'.$pager;
        }

        return $url;
    }

    private function mayUseCustomPath(): bool
    {
        return $this->useCustomHostPath
            && '' !== $this->currentHost // we have a request
            && null !== $this->apps->getCurrentPage() // a page is loaded
            && '' !== $this->apps->getCurrentPage()->getHost()
            && ! $this->apps->get()->isMainHost($this->currentHost);
    }

    /**
     * Set the value of isLive.
     */
    public function setUseCustomHostPath(bool $useCustomHostPath = true): self
    {
        $this->useCustomHostPath = $useCustomHostPath;

        return $this;
    }

    /**
     * Get the value of router.
     */
    public function getRouter(): SfRouterInterface
    {
        return $this->router;
    }
}
