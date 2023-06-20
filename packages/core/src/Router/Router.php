<?php

namespace Pushword\Core\Router;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\PageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface as SfRouterInterface;

final class Router implements RouterInterface
{
    private bool $useCustomHostPath = true;

    private readonly string $currentHost;

    public function __construct(
        private readonly SfRouterInterface $router,
        private readonly AppPool $apps,
        RequestStack $requestStack
    ) {
        $this->currentHost = null !== $requestStack->getCurrentRequest() ? $requestStack->getCurrentRequest()->getHost() : '';
    }

    /**
     * This function assume you are usin /X for X pages's home
     * and / for YY page home if your default language is YY
     * X/Y may be en/fr/...
     */
    public function generatePathForHomePage(PageInterface $page = null, bool $canonical = false): string
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
            }

            if (null !== $page && ! $this->apps->sameHost($page->getHost())) {
                // maybe we force canonical - useful for views
                $canonical = true;
            }
        }

        if ($canonical && null !== $page) {
            $baseUrl = $this->apps->getAppValue('baseUrl', $page->getHost());
        }

        $url = ($baseUrl ?? '').$this->router->generate(self::PATH, ['slug' => $slug]);

        if (null !== $pager && '1' !== (string) $pager) {
            return rtrim($url, '/').'/'.$pager;
        }

        return $url;
    }

    public function mayUseCustomPath(): bool
    {
        if (! $this->useCustomHostPath) {
            return false;
        }

        if ('' === $this->currentHost) {
            return false;
        }

        if (null === $this->apps->getCurrentPage()) {
            return false;
        }

        if ('' === $this->apps->getCurrentPage()->getHost()) {
            return false;
        }

        return ! $this->apps->get()->isMainHost($this->currentHost);
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
