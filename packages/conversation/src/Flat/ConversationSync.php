<?php

declare(strict_types=1);

namespace Pushword\Conversation\Flat;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\ConversationSyncInterface;
use Symfony\Component\Filesystem\Filesystem;

final class ConversationSync implements ConversationSyncInterface
{
    private ?bool $globalMustImportCache = null;

    private bool $globalExported = false;

    private bool $globalImported = false;

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly FlatFileContentDirFinder $contentDirFinder,
        public readonly ConversationImporter $importer,
        public readonly ConversationExporter $exporter,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function sync(?string $host = null, bool $forceExport = false): void
    {
        if (! $forceExport && $this->mustImport($host)) {
            $this->import($host);

            return;
        }

        $this->export($host);
    }

    public function import(?string $host = null): void
    {
        $isGlobalMode = $this->isGlobalMode($host);

        // In global mode, the CSV is identical for all hosts — import once
        if ($isGlobalMode && $this->globalImported) {
            return;
        }

        $this->importer->import($host);

        if ($isGlobalMode) {
            $this->globalImported = true;
        }
    }

    public function export(?string $host = null): void
    {
        $isGlobalMode = $this->isGlobalMode($host);

        // In global mode, the CSV is identical for all hosts — export once
        if ($isGlobalMode && $this->globalExported) {
            return;
        }

        if ($isGlobalMode) {
            $this->globalExported = true;
        }

        // Skip if CSV is already up to date (no messages changed since last export)
        $csvPath = $this->getCsvPath($host);
        if ($this->filesystem->exists($csvPath)) {
            $lastMessage = $this->importer->getLastUpdatedMessage(
                $isGlobalMode ? null : $this->resolveHost($host),
            );

            if (null !== $lastMessage && filemtime($csvPath) >= $lastMessage->updatedAt->getTimestamp()) { // @phpstan-ignore method.nonObject
                return;
            }
        }

        $this->exporter->export($host);

        // Sync CSV timestamp with last message to prevent import/export cycles
        $csvPath = $this->getCsvPath($host);
        if ($this->filesystem->exists($csvPath)) {
            $lastMessage = $this->importer->getLastUpdatedMessage(
                $isGlobalMode ? null : $this->resolveHost($host),
            );

            if (null !== $lastMessage) {
                touch($csvPath, $lastMessage->updatedAt->getTimestamp()); // @phpstan-ignore method.nonObject
            }
        }
    }

    public function mustImport(?string $host = null): bool
    {
        $isGlobalMode = $this->isGlobalMode($host);

        // In global mode, the result is the same for all hosts
        if ($isGlobalMode && null !== $this->globalMustImportCache) {
            return $this->globalMustImportCache;
        }

        $csvPath = $this->getCsvPath($host);

        if (! $this->filesystem->exists($csvPath)) {
            $result = false;
        } elseif (null === ($lastMessage = $this->importer->getLastUpdatedMessage(
            $isGlobalMode ? null : $this->resolveHost($host),
        ))) {
            $result = true;
        } else {
            $result = filemtime($csvPath) > $lastMessage->updatedAt->getTimestamp(); // @phpstan-ignore method.nonObject
        }

        if ($isGlobalMode) {
            $this->globalMustImportCache = $result;
        }

        return $result;
    }

    private function isGlobalMode(?string $host): bool
    {
        $app = null !== $host
            ? $this->apps->switchSite($host)->get()
            : $this->apps->get();

        return (bool) $app->get('flat_conversation_global');
    }

    private function getCsvPath(?string $host): string
    {
        if ($this->isGlobalMode($host)) {
            return $this->contentDirFinder->getBaseDir().'/conversation.csv';
        }

        return $this->contentDirFinder->get($this->resolveHost($host)).'/conversation.csv';
    }

    private function resolveHost(?string $host): string
    {
        $app = null !== $host
            ? $this->apps->switchSite($host)->get()
            : $this->apps->get();

        return $app->getMainHost();
    }
}
