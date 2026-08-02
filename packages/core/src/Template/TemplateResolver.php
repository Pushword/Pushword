<?php

namespace Pushword\Core\Template;

use InvalidArgumentException;
use Pushword\Core\Site\SiteConfig;
use Symfony\Contracts\Cache\CacheInterface;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;

final class TemplateResolver
{
    /**
     * In-memory memo of resolved views, keyed by cache key. The resolution is a
     * pure function of host/path/fallback and the on-disk template set (stable for
     * a process' lifetime), so it is safe to keep across requests in a worker.
     * Collapses the hundreds of identical resolve() calls a media-rich page makes
     * (getView() runs once per rendered <picture>) into array reads instead of one
     * cache.app filesystem read each.
     *
     * @var array<string, string>
     */
    private array $memo = [];

    public function __construct(private readonly Twig $twig, private readonly CacheInterface $cache)
    {
    }

    public function resolve(
        SiteConfig $site,
        ?string $path = null,
        string $fallback = '@Pushword',
    ): string {
        $cacheKey =
          'pushword.view.'.md5($site->getMainHost().'|'.$path.'|'.$fallback);

        return $this->memo[$cacheKey] ??= $this->cache->get(
            $cacheKey,
            fn (): string => $this->doResolve($site, $path, $fallback),
        );
    }

    private function doResolve(SiteConfig $site, ?string $path, string $fallback): string
    {
        if (null === $path) {
            return $site->template.'/page/page.html.twig';
        }

        // A path that names its own namespace is already resolved. A namespace
        // with nothing behind it is a caller error: everything below would
        // prepend a second one to it.
        if (str_starts_with($path, '@')) {
            if (! str_contains($path, '/')) {
                throw new InvalidArgumentException('Invalid view name: '.$path);
            }

            return $path;
        }

        if ('none' === $path) {
            $path = '/page/raw.twig';
        }

        // Everything below prepends a namespace, so the leading slash cannot be
        // left to the caller: `abo.html.twig` — what a page's template property
        // or a `view()` call spells — would come back as `@Pushwordabo.html.twig`
        // and only fail at include time, several frames from here.
        $path = '/'.ltrim($path, '/');

        $overridden = $this->findOverride($site, $path);
        if (null !== $overridden) {
            return $overridden;
        }

        $name = $site->template.$path;

        try {
            $this->twig->load($name);

            return $name;
        } catch (LoaderError) {
            return $fallback.$path;
        }
    }

    /** $name is normalized by doResolve(): no namespace, one leading slash. */
    private function findOverride(SiteConfig $site, string $name): ?string
    {
        $templateDir = $site->getStr('template_dir');

        // 1. Host-specific override
        $hostOverride = $templateDir.'/'.$site->getMainHost().$name;
        if (file_exists($hostOverride)) {
            return '/'.$site->getMainHost().$name;
        }

        // 1b. Fallback host overrides
        foreach ($site->getArray('view_fallback_hosts') as $fallbackHost) {
            \assert(\is_string($fallbackHost));
            $fallbackOverride = $templateDir.'/'.$fallbackHost.$name;
            if (file_exists($fallbackOverride)) {
                return '/'.$fallbackHost.$name;
            }
        }

        // 2. Theme-specific override
        $themeOverride = $templateDir.'/'.ltrim($site->template, '@').$name;
        if (file_exists($themeOverride)) {
            return '/'.ltrim($site->template, '@').$name;
        }

        // 3. Pushword core override
        $pushwordOverride = $templateDir.'/pushword'.$name;
        if (file_exists($pushwordOverride)) {
            return '/pushword'.$name;
        }

        // 4. Global override
        $globalOverride = $templateDir.$name;
        if (file_exists($globalOverride)) {
            return $name;
        }

        return null;
    }
}
