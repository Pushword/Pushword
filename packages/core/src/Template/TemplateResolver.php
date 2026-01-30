<?php

namespace Pushword\Core\Template;

use InvalidArgumentException;
use Pushword\Core\Site\SiteConfig;
use Symfony\Contracts\Cache\CacheInterface;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;

final readonly class TemplateResolver
{
    public function __construct(
        private Twig $twig,
        private CacheInterface $cache,
    ) {
    }

    public function resolve(SiteConfig $site, ?string $path = null, string $fallback = '@Pushword'): string
    {
        $cacheKey = 'pushword.view.'.md5($site->getMainHost().'|'.$path.'|'.$fallback);

        return $this->cache->get($cacheKey, fn (): string => $this->doResolve($site, $path, $fallback));
    }

    private function doResolve(SiteConfig $site, ?string $path, string $fallback): string
    {
        if (null === $path) {
            return $site->getTemplate().'/page/page.html.twig';
        }

        if ($this->isFullPath($path)) {
            return $path;
        }

        if ('none' === $path) {
            $path = '/page/raw.twig';
        }

        $overridden = $this->findOverride($site, $path);
        if (null !== $overridden) {
            return $overridden;
        }

        $name = $site->getTemplate().$path;

        try {
            $this->twig->load($name);

            return $name;
        } catch (LoaderError) {
            return $fallback.$path;
        }
    }

    private function findOverride(SiteConfig $site, string $name): ?string
    {
        if (str_starts_with($name, '@')) {
            $namePart = explode('/', $name, 2);
            if (! isset($namePart[1])) {
                throw new InvalidArgumentException('Invalid view name: '.$name);
            }

            $name = $namePart[1];
        }

        $name = ('/' === $name[0] ? '' : '/').$name;

        $templateDir = $site->getStr('template_dir');

        // 1. Host-specific override
        $hostOverride = $templateDir.'/'.$site->getMainHost().$name;
        if (file_exists($hostOverride)) {
            return '/'.$site->getMainHost().$name;
        }

        // 2. Theme-specific override
        $themeOverride = $templateDir.'/'.ltrim($site->getTemplate(), '@').$name;
        if (file_exists($themeOverride)) {
            return '/'.ltrim($site->getTemplate(), '@').$name;
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

    private function isFullPath(string $path): bool
    {
        return str_starts_with($path, '@') && str_contains($path, '/');
    }
}
