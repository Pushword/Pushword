<?php

namespace Pushword\Flat;

use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\Event\FlatSyncCompletedEvent;
use Pushword\Flat\Sync\MediaSync;
use Pushword\Flat\Sync\PageSync;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class FlatFileSync
{
    public function __construct(
        public PageSync $pageSync,
        public MediaSync $mediaSync,
        private EventDispatcherInterface $eventDispatcher,
        private AppPool $apps,
    ) {
    }

    public function sync(?string $host = null, bool $forceExport = false, ?string $exportDir = null): void
    {
        $this->mediaSync->sync($host, $forceExport, $exportDir);
        $this->pageSync->sync($host, $forceExport, $exportDir);

        $this->dispatchEvent($host);
    }

    public function import(?string $host = null): void
    {
        $this->mediaSync->import($host);
        $this->pageSync->import($host);

        $this->dispatchEvent($host);
    }

    public function export(?string $host = null, ?string $exportDir = null, bool $force = false): void
    {
        $this->mediaSync->export($host, $force, $exportDir);
        $this->pageSync->export($host, $force, $exportDir);

        $this->dispatchEvent($host);
    }

    private function dispatchEvent(?string $host): void
    {
        $app = null !== $host
            ? $this->apps->switchCurrentApp($host)->get()
            : $this->apps->get();

        $this->eventDispatcher->dispatch(new FlatSyncCompletedEvent($app->getMainHost()));
    }
}
