<?php

namespace Pushword\Core\Router;

use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\Routing\RouterInterface as SfRouterInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;

final class PushwordRouteGenerator implements ResetInterface
{
    public const string PATH = 'pushword_page';

    public const string CUSTOM_HOST_PATH = 'custom_host_pushword_page';

    private bool $useCustomHostPath = true;

    /**
     * What reset() restores. Stays true on a live kernel (worker-mode safety);
     * the static generator pins false on its render kernel — see setUseCustomHostPath().
     */
    private bool $useCustomHostPathAfterReset = true;

    public function __construct(
        private readonly SfRouterInterface $router,
        private readonly SiteRegistry $apps,
    ) {
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
            if ($page->locale !== $this->apps->get()->getLocale()) {
                $homepage->setSlug($page->locale);
            }

            $homepage->host = $page->host;
        } else {
            $homepage->locale = $this->apps->get()->getLocale();
            $homepage->host = $this->apps->get()->getMainHost();
        }

        return $this->generate($homepage, $canonical);
    }

    public function generate(
        Page|string $slug = 'homepage',
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
                    'host' => $host ?? $this->apps->requirePage()->host,
                    'slug' => $slug,
                ]);
            }

            if (null !== $page && ! $this->apps->sameHost($page->host)
                || null !== $host && ! $this->apps->sameHost($host)) {
                // maybe we force canonical - useful for views
                $canonical = true;
            }
        }

        if ($canonical && (null !== $page || null !== $host)) {
            $baseUrl = $this->apps->get(null !== $page ? $page->host : $host)->getStr('baseUrl', '');
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

        $currentHost = $this->apps->getCurrentHost() ?? '';
        if ('' === ($host ?? $currentHost)) {
            return false;
        }

        if (null === $host && null === $this->apps->getCurrentPage()) {
            return false;
        }

        if ('' === ($host ?? $this->apps->getCurrentPage()?->host)) {
            return false;
        }

        // Custom_host route means HTTP host ≠ site host (e.g. 127.0.0.1/{host}/{slug}): keep prefixing
        if (str_contains($this->apps->getCurrentRoute() ?? '', 'custom_host_')) {
            return ! $this->apps->isDefaultHost($host ?? $currentHost);
        }

        if ($this->apps->sameHost($host ?? $this->apps->getCurrentPage()?->host)) {
            return false;
        }

        return ! $this->apps->isDefaultHost($host ?? $currentHost);
    }

    /**
     * With $pin, the value also becomes what reset() restores. A plain set does
     * NOT survive rendering: Kernel::handle() marks services for reset, so the
     * NEXT handle() runs the services_resetter inside its boot() — after any
     * value set before the call, and before the request renders. The static
     * generator's render kernel only ever renders host-less static HTML, so it
     * pins false; a live kernel must never pin (see reset()).
     */
    public function setUseCustomHostPath(bool $useCustomHostPath = true, bool $pin = false): self
    {
        $this->useCustomHostPath = $useCustomHostPath;
        if ($pin) {
            $this->useCustomHostPathAfterReset = $useCustomHostPath;
        }

        return $this;
    }

    /**
     * Worker-mode safety (kernel.reset): restore the live default so a synchronous
     * static regeneration during a request (AbstractGenerator sets this to false)
     * never leaks into the next request served by the same worker — which would
     * render every link without its /{host}/ prefix, like the static site.
     */
    public function reset(): void
    {
        $this->useCustomHostPath = $this->useCustomHostPathAfterReset;
    }

    /**
     * Get the value of router.
     */
    public function getRouter(): SfRouterInterface
    {
        return $this->router;
    }
}
