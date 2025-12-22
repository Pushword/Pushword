<?php

namespace Pushword\StaticGenerator\Generator;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Pushword\Core\Entity\Page;
use Pushword\Core\Twig\MediaExtension;
use Pushword\StaticGenerator\IncrementalGeneratorInterface;

class PagesGenerator extends PageGenerator implements IncrementalGeneratorInterface
{
    private bool $incremental = false;

    public function setIncremental(bool $incremental): void
    {
        $this->incremental = $incremental;
    }

    #[Override]
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $this->preloadMediaCache();
        $pages = $this->getPageRepository()->getPublishedPages($this->app->getMainHost());

        $stateManager = $this->staticAppGenerator->getStateManager();
        $hostName = $this->app->getMainHost();

        // Track current slugs for cleanup
        $currentSlugs = [];

        foreach ($pages as $page) {
            $currentSlugs[] = $page->getSlug();

            // In incremental mode, skip pages that haven't changed
            if ($this->incremental && ! $this->needsRegeneration($page, $hostName)) {
                continue;
            }

            $this->generatePage($page);

            // Update state for this page
            $updatedAt = $page->getUpdatedAt();
            if (null !== $updatedAt) {
                $stateManager->setPageState($hostName, $page->getSlug(), $this->toImmutable($updatedAt));
            }
        }

        // Cleanup deleted pages from state
        if ($this->incremental) {
            $stateManager->cleanupDeletedPages($hostName, $currentSlugs);
        }

        $this->finishCompression();
    }

    /**
     * Check if a page needs regeneration.
     */
    private function needsRegeneration(Page $page, string $host): bool
    {
        // Redirections are always processed (they update the redirection manager)
        if ($page->hasRedirection()) {
            return true;
        }

        $updatedAt = $page->getUpdatedAt();
        if (null === $updatedAt) {
            return true;
        }

        $stateManager = $this->staticAppGenerator->getStateManager();

        return $stateManager->needsRegeneration(
            $host,
            $page->getSlug(),
            $this->toImmutable($updatedAt)
        );
    }

    /**
     * Convert DateTimeInterface to DateTimeImmutable.
     */
    private function toImmutable(DateTimeInterface $dateTime): DateTimeImmutable
    {
        return $dateTime instanceof DateTimeImmutable
            ? $dateTime
            : DateTimeImmutable::createFromInterface($dateTime);
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
