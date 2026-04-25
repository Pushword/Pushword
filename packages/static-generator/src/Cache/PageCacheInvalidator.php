<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Cache;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Cache\Message\PageCacheRefreshMessage;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::postPersist, entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Page::class)]
#[AsEntityListener(event: Events::preRemove, entity: Page::class)]
final readonly class PageCacheInvalidator
{
    public function __construct(
        private MessageBusInterface $bus,
        private SiteRegistry $apps,
        private PageCacheSuppressor $suppressor,
        private PageCacheFileManager $fileManager,
    ) {
    }

    public function postPersist(Page $page): void
    {
        $this->queueRefresh($page);
    }

    public function postUpdate(Page $page): void
    {
        $this->queueRefresh($page);
    }

    public function preRemove(Page $page): void
    {
        if ($this->suppressor->isSuppressed() || ! $this->isCacheSite($page)) {
            return;
        }

        $this->fileManager->delete($page);
    }

    private function queueRefresh(Page $page): void
    {
        if ($this->suppressor->isSuppressed() || ! $this->isCacheSite($page) || null === $page->id) {
            return;
        }

        $this->bus->dispatch(new PageCacheRefreshMessage($page->id));
    }

    private function isCacheSite(Page $page): bool
    {
        $app = $this->apps->findByHost($page->host);

        return null !== $app && StaticAppGenerator::isCacheMode($app);
    }
}
