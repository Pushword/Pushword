<?php

namespace Pushword\Core\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaUsageRepository;
use Pushword\Core\Service\MediaUsageTracker;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Keeps a page's media usage current on write, so nothing has to rescan the corpus
 * to answer "where is this media used".
 *
 * Populating on write rather than on read is what makes the answer cheap; the cost
 * of that choice is one extra SELECT per page save, and only when one of the four
 * fields a usage can come from actually changed.
 */
#[AsEntityListener(event: Events::postPersist, method: 'pagePostPersist', entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'pagePostUpdate', entity: Page::class)]
#[AsEntityListener(event: Events::preRemove, method: 'pagePreRemove', entity: Page::class)]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'preRemove', 'method' => 'mediaPreRemove'])]
final readonly class MediaUsageListener
{
    /**
     * The fields a usage row can be read from, plus `tags`, which feeds no row but
     * every inheriting media.
     */
    private const array WATCHED_FIELDS = ['mainContent', 'customProperties', 'mainImage', 'tags'];

    public function __construct(
        private MediaUsageTracker $mediaUsageTracker,
        private MediaUsageRepository $mediaUsageRepository,
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

    /** Its rows have nothing left to point at, and SQLite will not cascade them away. */
    public function mediaPreRemove(Media $media): void
    {
        if (null !== $media->id) {
            $this->mediaUsageRepository->deleteForMedia($media->id);
        }
    }
}
