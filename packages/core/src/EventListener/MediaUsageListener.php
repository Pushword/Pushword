<?php

namespace Pushword\Core\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaUsageRepository;
use Pushword\Core\Service\MediaUsageTracker;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Keeps a page's media usage current on write, so nothing has to rescan the corpus
 * to answer "where is this media used".
 *
 * Populating on write rather than on read is what makes the answer cheap; the cost
 * of that choice is one extra SELECT per page save, and only when one of the four
 * fields a usage can come from actually changed.
 *
 * A page write is not the only moment a usage can appear, though: a filename only
 * resolves against the media existing when the page is saved, so a media created
 * afterwards has to send the pages naming it back through extraction. That is what
 * the media side does — collected per flush rather than per media, because one
 * candidate scan per uploaded file is the pages × media cost this table exists to
 * remove.
 */
#[AsEntityListener(event: Events::postPersist, method: 'pagePostPersist', entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'pagePostUpdate', entity: Page::class)]
#[AsEntityListener(event: Events::preRemove, method: 'pagePreRemove', entity: Page::class)]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'postPersist', 'method' => 'mediaPostPersist'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'preRemove', 'method' => 'mediaPreRemove'])]
#[AsDoctrineListener(event: Events::postFlush)]
final class MediaUsageListener implements ResetInterface
{
    /**
     * The fields a usage row can be read from, plus `tags`, which feeds no row but
     * every inheriting media.
     */
    private const array WATCHED_FIELDS = ['mainContent', 'customProperties', 'mainImage', 'tags'];

    /**
     * The files this flush brought in, waiting for the flush to end. Held rather than
     * acted on: the rows they need are not committed yet, and a bulk import would pay
     * a scan per file.
     *
     * @var list<string>
     */
    private array $newFileNames = [];

    public function __construct(
        private readonly MediaUsageTracker $mediaUsageTracker,
        private readonly MediaUsageRepository $mediaUsageRepository,
    ) {
    }

    public function pagePostPersist(Page $page): void
    {
        $this->mediaUsageTracker->track($page);
    }

    public function pagePostUpdate(Page $page, PostUpdateEventArgs $event): void
    {
        $changeSet = $event->getObjectManager()->getUnitOfWork()->getEntityChangeSet($page);

        if ([] === array_intersect(self::WATCHED_FIELDS, array_keys($changeSet))) {
            return;
        }

        $usageChanged = $this->mediaUsageTracker->track($page);

        // A retag moves no usage row but every inheriting media, so the refresh
        // track() would have run has to be asked for here.
        if (! $usageChanged && isset($changeSet['tags']) && null !== $page->id) {
            $this->mediaUsageTracker->refreshPageTags($this->mediaUsageRepository->findMediaIdsForPage($page->id));
        }
    }

    public function pagePreRemove(Page $page): void
    {
        $this->mediaUsageTracker->untrack($page);
    }

    public function mediaPostPersist(Media $media): void
    {
        $this->newFileNames[] = $media->getFileName();
    }

    /**
     * Where the media side does its work: the inserts are written by now, so the
     * filename index rebuilds with them in it, and a flush that brought in fifty files
     * pays for one round of candidate scans rather than fifty.
     *
     * Nothing here flushes — every write goes to the connection directly — so this
     * cannot re-enter.
     */
    public function postFlush(): void
    {
        if ([] === $this->newFileNames) {
            return;
        }

        $fileNames = $this->newFileNames;
        $this->newFileNames = [];

        $this->mediaUsageTracker->trackPagesReferencing($fileNames);
    }

    /** Its rows have nothing left to point at, and SQLite will not cascade them away. */
    public function mediaPreRemove(Media $media): void
    {
        if (null !== $media->id) {
            $this->mediaUsageRepository->deleteForMedia($media->id);
        }
    }

    /** A flush that threw between the persist and the drain must not leave its names to the next request. */
    public function reset(): void
    {
        $this->newFileNames = [];
    }
}
