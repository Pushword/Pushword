<?php

declare(strict_types=1);

namespace Pushword\Flat;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Sync\ConversationSyncInterface;
use Pushword\Flat\Sync\MediaSync;
use Pushword\Flat\Sync\PageSync;
use Pushword\Flat\Sync\UserSync;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

final class FlatFileSync
{
    private ?Stopwatch $stopwatch = null;

    public function __construct(
        public readonly PageSync $pageSync,
        public readonly MediaSync $mediaSync,
        private readonly SiteRegistry $apps,
        private readonly ?ConversationSyncInterface $conversationSync = null,
        private readonly ?UserSync $userSync = null,
    ) {
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->mediaSync->setOutput($output);
        $this->pageSync->setOutput($output);
        $this->userSync?->setOutput($output);
    }

    public function setStopwatch(Stopwatch $stopwatch): void
    {
        $this->stopwatch = $stopwatch;
        $this->mediaSync->setStopwatch($stopwatch);
        $this->pageSync->setStopwatch($stopwatch);
    }

    public function sync(?string $host = null, bool $forceExport = false, ?string $exportDir = null, string $entity = 'all'): void
    {
        if (\in_array($entity, ['media', 'all'], true)) {
            $this->stopwatch?->start('media.sync');
            $this->mediaSync->sync($host, $forceExport, $exportDir);
            $this->stopwatch?->stop('media.sync');
        }

        if (\in_array($entity, ['page', 'all'], true)) {
            $this->stopwatch?->start('page.sync');
            $this->pageSync->sync($host, $forceExport, $exportDir);
            $this->stopwatch?->stop('page.sync');
        }

        if (\in_array($entity, ['conversation', 'all'], true) && null !== $this->conversationSync) {
            $this->stopwatch?->start('conversation.sync');
            $this->conversationSync->sync($host, $forceExport);
            $this->stopwatch?->stop('conversation.sync');
        }

        if (\in_array($entity, ['user', 'all'], true) && null !== $this->userSync) {
            $this->stopwatch?->start('user.sync');
            $this->userSync->import();
            $this->stopwatch?->stop('user.sync');
        }
    }

    public function import(?string $host = null, string $entity = 'all', bool $force = false): void
    {
        if (\in_array($entity, ['media', 'all'], true)) {
            $this->stopwatch?->start('media.sync');
            $this->mediaSync->import($host);
            $this->stopwatch?->stop('media.sync');
        }

        if (\in_array($entity, ['page', 'all'], true)) {
            $this->stopwatch?->start('page.sync');
            $this->pageSync->import($host, $force);
            $this->stopwatch?->stop('page.sync');
        }

        if (\in_array($entity, ['conversation', 'all'], true) && null !== $this->conversationSync) {
            $this->stopwatch?->start('conversation.sync');
            $this->conversationSync->import($host);
            $this->stopwatch?->stop('conversation.sync');
        }

        if (\in_array($entity, ['user', 'all'], true) && null !== $this->userSync) {
            $this->stopwatch?->start('user.sync');
            $this->userSync->import();
            $this->stopwatch?->stop('user.sync');
        }
    }

    public function export(?string $host = null, ?string $exportDir = null, bool $force = false, string $entity = 'all'): void
    {
        if (\in_array($entity, ['media', 'all'], true)) {
            $this->stopwatch?->start('media.sync');
            $this->mediaSync->export($host, $force, $exportDir);
            $this->stopwatch?->stop('media.sync');
        }

        if (\in_array($entity, ['page', 'all'], true)) {
            $this->stopwatch?->start('page.sync');
            $this->pageSync->export($host, $force, $exportDir);
            $this->stopwatch?->stop('page.sync');
        }

        if (\in_array($entity, ['conversation', 'all'], true) && null !== $this->conversationSync) {
            $this->stopwatch?->start('conversation.sync');
            $this->conversationSync->export($host);
            $this->stopwatch?->stop('conversation.sync');
        }
    }

    /** @return string[] */
    public function getHosts(): array
    {
        return $this->apps->getHosts();
    }
}
