<?php

namespace Pushword\Search\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Search\Message\ReindexPageMessage;
use Pushword\Search\Message\RemovePageMessage;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Incremental search index updates: mirrors the page-cache invalidation
 * pattern, queuing a single-page reindex on save and a removal on delete.
 */
#[AsEntityListener(event: Events::postPersist, entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Page::class)]
#[AsEntityListener(event: Events::preRemove, entity: Page::class)]
final readonly class PageIndexInvalidator
{
    public function __construct(
        private MessageBusInterface $bus,
        private SiteRegistry $siteRegistry,
        private PageCacheSuppressor $suppressor,
        private bool $incremental,
    ) {
    }

    public function postPersist(Page $page): void
    {
        $this->queueReindex($page);
    }

    public function postUpdate(Page $page): void
    {
        $this->queueReindex($page);
    }

    public function preRemove(Page $page): void
    {
        if (! $this->shouldRun($page) || null === $page->id) {
            return;
        }

        $this->bus->dispatch(new RemovePageMessage($page->id, $page->host));
    }

    private function queueReindex(Page $page): void
    {
        if (! $this->shouldRun($page) || null === $page->id) {
            return;
        }

        $this->bus->dispatch(new ReindexPageMessage($page->id));
    }

    private function shouldRun(Page $page): bool
    {
        return $this->incremental
            && ! $this->suppressor->isSuppressed()
            && null !== $this->siteRegistry->findByHost($page->host);
    }
}
