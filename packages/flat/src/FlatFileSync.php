<?php

namespace Pushword\Flat;

use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\Event\FlatSyncCompletedEvent;
use Pushword\Flat\Sync\MediaSync;
use Pushword\Flat\Sync\PageSync;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class FlatFileSync
{
    private ?Stopwatch $stopwatch = null;

    public function __construct(
        public readonly PageSync $pageSync,
        public readonly MediaSync $mediaSync,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AppPool $apps,
    ) {
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->mediaSync->setOutput($output);
        $this->pageSync->setOutput($output);
    }

    public function setStopwatch(Stopwatch $stopwatch): void
    {
        $this->stopwatch = $stopwatch;
        $this->mediaSync->setStopwatch($stopwatch);
        $this->pageSync->setStopwatch($stopwatch);
    }

    public function sync(?string $host = null, bool $forceExport = false, ?string $exportDir = null): void
    {
        $this->stopwatch?->start('media.sync');
        $this->mediaSync->sync($host, $forceExport, $exportDir);
        $this->stopwatch?->stop('media.sync');

        $this->stopwatch?->start('page.sync');
        $this->pageSync->sync($host, $forceExport, $exportDir);
        $this->stopwatch?->stop('page.sync');

        $this->dispatchEvent($host);
    }

    public function import(?string $host = null, bool $skipId = false): void
    {
        $this->stopwatch?->start('media.sync');
        $this->mediaSync->import($host);
        $this->stopwatch?->stop('media.sync');

        $this->stopwatch?->start('page.sync');
        $this->pageSync->import($host, $skipId);
        $this->stopwatch?->stop('page.sync');

        $this->dispatchEvent($host);
    }

    public function export(?string $host = null, ?string $exportDir = null, bool $force = false, bool $skipId = false): void
    {
        $this->stopwatch?->start('media.sync');
        $this->mediaSync->export($host, $force, $exportDir);
        $this->stopwatch?->stop('media.sync');

        $this->stopwatch?->start('page.sync');
        $this->pageSync->export($host, $force, $exportDir, $skipId);
        $this->stopwatch?->stop('page.sync');

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
