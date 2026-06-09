<?php

namespace Pushword\Flat\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Flat\Service\DeferredExportProcessor;

#[AsEntityListener(event: Events::postPersist, entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Page::class)]
#[AsEntityListener(event: Events::postRemove, entity: Page::class)]
#[AsEntityListener(event: Events::postPersist, entity: Media::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Media::class)]
final readonly class FlatExportEntityListener
{
    public function __construct(
        private DeferredExportProcessor $exportProcessor,
    ) {
    }

    public function postPersist(Page|Media $entity): void
    {
        $this->exportProcessor->queue($entity, 'persist');
    }

    public function postUpdate(Page|Media $entity): void
    {
        $this->exportProcessor->queue($entity, 'update');
    }

    /**
     * A removed page no longer writes its own .md, so without this the deletion
     * would schedule no export and the now-orphaned file would linger (then get
     * re-imported on the next `--mode=import`). Queuing a host export lets the
     * full export's orphan cleanup remove the stale file.
     */
    public function postRemove(Page $entity): void
    {
        $this->exportProcessor->queue($entity, 'remove');
    }
}
