<?php

namespace Pushword\Core\Router;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface as SfRouterInterface;
use Twig\Attribute\AsTwigFunction;

final class PushwordRouteGenerator
{
    /**
     * @var string
     */
    public const string PATH = 'pushword_page';

    /**
     * @var string
     */
    public const string CUSTOM_HOST_PATH = 'custom_host_pushword_page';

    private bool $useCustomHostPath = true;

    private readonly string $currentHost;

    public function __construct(
        private readonly SfRouterInterface $router,
        private readonly AppPool $apps,
        RequestStack $requestStack
    ) {
        $currentRequest = $requestStack->getCurrentRequest();
        $this->currentHost = null !== $currentRequest ? $currentRequest->getHost() : '';
    }

    /**
     * This function assume you are usin /X for X pages's home
     * and / for YY page home if your default language is YY
     * X/Y may be en/fr/...
     */
    #[AsTwigFunction('homepage')]
    public function generatePathForHomePage(?Page $page = null, bool $canonical = false): string
    {
        $homepage = new Page()->setSlug('');

        if (null !== $page) {
            if ($page->getLocale() !== $this->apps->get()->getLocale()) {
                $homepage->setSlug($page->getLocale());
            }

            $homepage->setHost($page->getHost());
        } else {
            $homepage->setLocale($this->apps->get()->getLocale())->setHost($this->apps->get()->getMainHost());
        }

        return $this->generate($homepage, $canonical);
    }

    /**
     * @param string|Page $slug
     */
    public function generate(
        $slug = 'homepage',
        bool $canonical = false,
        ?int $pager = null,
        ?string $host = null,
        bool $forceUseCustomPath = false,
    ): string {
        $page = null;

        if ($slug instanceof Page) {
            $page = $slug;
            $slug = $slug->getRealSlug();
        } elseif ('homepage' === $slug) {
            $slug = '';
        }

        if (! $canonical) {
            if ($forceUseCustomPath || $this->mayUseCustomPath($host)) {
                return $this->router->generate(self::CUSTOM_HOST_PATH, [
                    'host' => $host ?? $this->apps->safegetCurrentPage()->getHost(),
                    'slug' => $slug,
                ]);
            }

            if (null !== $page && ! $this->apps->sameHost($page->getHost())
                || null !== $host && ! $this->apps->sameHost($host)) {
                // maybe we force canonical - useful for views
                $canonical = true;
            }
        }

        if ($canonical && (null !== $page || null !== $host)) {
            $baseUrl = $this->apps->get(null !== $page ? $page->getHost() : $host)->getStr('baseUrl', '');
        } else {
            $baseUrl = '';
        }

        $url = $baseUrl.$this->router->generate(self::PATH, ['slug' => $slug]);

        if (null !== $pager && 1 !== $pager) {
            return rtrim($url, '/').'/'.$pager;
        }

        return $url;
    }

    public function mayUseCustomPath(?string $host = null): bool
    {
        if (! $this->useCustomHostPath) {
            return false;
        }

        if ('' === ($host ?? $this->currentHost)) {
            return false;
        }

        if (null === $host && null === $this->apps->getCurrentPage()) {
            return false;
        }

        if ('' === ($host ?? $this->apps->getCurrentPage()?->getHost())) {
            return false;
        }

        return ! $this->apps->isDefaultHost($host ?? $this->currentHost);
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
