<?php

namespace Pushword\StaticGenerator\Cache;

use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Computes the on-disk path of a cached page and deletes it (and its compressed sidecars).
 */
readonly class PageCacheFileManager
{
    /** @var string[] */
    private const array COMPRESSION_SUFFIXES = ['', '.gz', '.br', '.zst'];

    private Filesystem $filesystem;

    public function __construct(
        private StaticAppGenerator $staticAppGenerator,
        private SiteRegistry $apps,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function isCacheable(Page $page): bool
    {
        $app = $this->apps->findByHost($page->host);
        if (null === $app || ! StaticAppGenerator::isCacheMode($app)) {
            return false;
        }

        if (! $page->isPublished()) {
            return false;
        }

        if ($page->hasRedirection()) {
            return false;
        }

        return false !== $page->getCustomProperty('cache');
    }

    public function delete(Page $page): void
    {
        $path = $this->resolvePath($page);
        if (null === $path) {
            return;
        }

        foreach (self::COMPRESSION_SUFFIXES as $suffix) {
            $this->filesystem->remove($path.$suffix);
        }
    }

    /**
     * Returns the primary `.html` path (or `.xml`/`.json` when the slug carries the extension).
     * Null when the page's host has no cache-mode site config.
     */
    private function resolvePath(Page $page): ?string
    {
        $app = $this->apps->findByHost($page->host);
        if (null === $app) {
            return null;
        }

        $slug = '' === $page->getRealSlug() ? 'index' : $page->getRealSlug();
        $cacheDir = $this->staticAppGenerator->getCacheDir($app);

        if (str_ends_with($slug, '.json') || str_ends_with($slug, '.xml')) {
            return $cacheDir.'/'.$slug;
        }

        return $cacheDir.'/'.$slug.'.html';
    }
}
