<?php

declare(strict_types=1);

namespace Pushword\Flat\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Flat\Service\DeferredExportProcessor;

#[AsEntityListener(event: Events::postPersist, entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Page::class)]
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
}
