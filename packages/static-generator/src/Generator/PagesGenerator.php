<?php

namespace Pushword\StaticGenerator\Generator;

use Override;
use Pushword\Core\Twig\MediaExtension;

class PagesGenerator extends PageGenerator
{
    #[Override]
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $this->preloadMediaCache();
        $pages = $this->getPageRepository()->getPublishedPages($this->app->getMainHost());

        foreach ($pages as $page) {
            $this->generatePage($page);
        }

        $this->finishCompression();
    }

    /**
     * Preload all Media entities into a cache to avoid N+1 queries during rendering.
     */
    private function preloadMediaCache(): void
    {
        /** @var MediaExtension $mediaExtension */
        $mediaExtension = static::getKernel()->getContainer()->get(MediaExtension::class);
        $mediaExtension->preloadMediaCache();
    }

    public function generatePageBySlug(string $slug, ?string $host = null): void
    {
        parent::generate($host);

        $this->preloadMediaCache();
        $pages = $this->getPageRepository()
            ->getPublishedPages($this->app->getMainHost(), ['slug', 'LIKE', $slug]);

        foreach ($pages as $page) {
            $this->generatePage($page);
        }

        $this->finishCompression();
    }
}
