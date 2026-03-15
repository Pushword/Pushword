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
        $this->importer->import($host);
    }

    public function export(?string $host = null): void
    {
        $this->exporter->export($host);
    }

    public function mustImport(?string $host = null): bool
    {
        $app = null !== $host
            ? $this->apps->switchSite($host)->get()
            : $this->apps->get();

        $isGlobalMode = (bool) $app->get('flat_conversation_global');

        // In global mode, the result is the same for all hosts
        if ($isGlobalMode && null !== $this->globalMustImportCache) {
            return $this->globalMustImportCache;
        }

        $csvPath = $isGlobalMode
            ? $this->contentDirFinder->getBaseDir().'/conversation.csv'
            : $this->contentDirFinder->get($app->getMainHost()).'/conversation.csv';

        if (! $this->filesystem->exists($csvPath)) {
            $result = false;
        } elseif (null === ($lastMessage = $this->importer->getLastUpdatedMessage(
            $isGlobalMode ? null : $app->getMainHost(),
        ))) {
            $result = true;
        } else {
            $result = filemtime($csvPath) > $lastMessage->updatedAt->getTimestamp(); // @phpstan-ignore method.nonObject (property hook guarantees non-null)
        }

        if ($isGlobalMode) {
            $this->globalMustImportCache = $result;
        }

        return $result;
    }
}
