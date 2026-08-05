<?php

namespace Pushword\Core\Service;

use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\MediaUsageRepository;
use Pushword\Core\Repository\PageRepository;

/**
 * Keeps `media_usage` — and the tags media inherit from it — in step with the pages.
 *
 * One entry point for the writers that exist: the listener, on every page write and
 * on the media a flush brought in, and `pw:media:usage:rebuild` for the first run and
 * after a bulk import. All go through here so the derived tags can never be refreshed
 * by one and forgotten by another.
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
        private PageRepository $pageRepository,
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
     * Re-track the pages naming one of these files, for media that have just appeared.
     *
     * Extraction answers a filename against the media existing when the page is saved,
     * so a media created afterwards leaves every page naming it with no row for it —
     * and `pw:media:clean-unused --force` then reads a live image as an orphan. Two
     * ordinary flows land there: a page written before its image is uploaded, and a
     * media deleted and re-uploaded corrected under the same name, which the pages
     * keep rendering through its filename under a new id.
     *
     * A media appearing is the second moment the answer can change, and this asks the
     * question again then. Batched by the caller rather than run per media: the point
     * of the usage table is that nothing costs pages × media.
     *
     * @param list<string> $fileNames
     */
    public function trackPagesReferencing(array $fileNames): void
    {
        $touched = [];

        foreach ($this->pageRepository->findContentRowsReferencing($fileNames) as $row) {
            $usages = $this->extractor->extract($row['mainContent'], $row['customProperties'], $row['mainImageId']);

            foreach ($this->mediaUsageRepository->replaceForPage($row['id'], $usages) as $mediaId) {
                $touched[$mediaId] = true;
            }
        }

        $this->refreshPageTags(array_keys($touched));
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
