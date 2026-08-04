<?php

namespace Pushword\Core\Service;

use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\MediaUsageRepository;

/**
 * Keeps `media_usage` — and the tags media inherit from it — in step with the pages.
 *
 * One entry point for the two writers that exist: the listener on every page write,
 * and `pw:media:usage:rebuild` for the first run and after a bulk import. Both go
 * through here so the derived tags can never be refreshed by one and forgotten by
 * the other.
 *
 * Every write is conditional on something having actually changed. A page saved
 * without its images moving costs one SELECT and stops there, which is what makes
 * the listener affordable on a flat import of thousands of pages.
 */
final readonly class MediaUsageTracker
{
    private const int REFRESH_CHUNK = 500;

    public function __construct(
        private MediaUsageExtractor $extractor,
        private MediaUsageRepository $mediaUsageRepository,
        private MediaRepository $mediaRepository,
    ) {
    }

    /** @return bool whether the stored usage changed */
    public function track(Page $page): bool
    {
        if (null === $page->id) {
            return false;
        }

        $touched = $this->mediaUsageRepository->replaceForPage($page->id, $this->extractor->extractFromPage($page));

        if ([] === $touched) {
            return false;
        }

        $this->refreshPageTags($touched);

        return true;
    }

    /**
     * Drop a page's rows before it goes. The `ON DELETE CASCADE` on the join columns
     * would do it on MariaDB, but SQLite does not enforce foreign keys, and a usage
     * row surviving its page is a media that reads as used forever.
     */
    public function untrack(Page $page): void
    {
        if (null === $page->id) {
            return;
        }

        $this->refreshPageTags($this->mediaUsageRepository->deleteForPage($page->id));
    }

    /**
     * Recompute the tags these media inherit, and store only the ones that moved.
     *
     * Chunked because the rebuild hands it every media on the site, and an `IN` that
     * wide runs into SQLite's bound-parameter ceiling.
     *
     * @param list<int> $mediaIds
     */
    public function refreshPageTags(array $mediaIds): void
    {
        foreach (array_chunk($mediaIds, self::REFRESH_CHUNK) as $chunk) {
            $this->refreshPageTagsChunk($chunk);
        }
    }

    /** @param list<int> $mediaIds */
    private function refreshPageTagsChunk(array $mediaIds): void
    {
        $computed = $this->mediaUsageRepository->findPageTagsByMedia($mediaIds);
        $stored = $this->mediaRepository->findPageTags($mediaIds);

        foreach ($mediaIds as $mediaId) {
            // A media absent from the stored snapshot was deleted between the two
            // queries; writing its tags back would resurrect nothing but a no-op.
            if (! isset($stored[$mediaId])) {
                continue;
            }

            $pageTags = $computed[$mediaId] ?? [];
            if ($stored[$mediaId] === $pageTags) {
                continue;
            }

            $this->mediaRepository->updatePageTags($mediaId, $pageTags);
        }
    }
}
