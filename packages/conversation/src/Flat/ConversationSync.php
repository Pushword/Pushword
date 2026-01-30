<?php

declare(strict_types=1);

namespace Pushword\Conversation\Flat;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\ConversationSyncInterface;

use function Safe\filemtime;

final readonly class ConversationSync implements ConversationSyncInterface
{
    public function __construct(
        private SiteRegistry $apps,
        private FlatFileContentDirFinder $contentDirFinder,
        public ConversationImporter $importer,
        public ConversationExporter $exporter,
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

        $csvPath = $isGlobalMode
            ? $this->contentDirFinder->getBaseDir().'/conversation.csv'
            : $this->contentDirFinder->get($app->getMainHost()).'/conversation.csv';

        if (! file_exists($csvPath)) {
            return false;
        }

        $lastMessage = $this->importer->getLastUpdatedMessage(
            $isGlobalMode ? null : $app->getMainHost(),
        );

        if (null === $lastMessage) {
            return true;
        }

        return filemtime($csvPath) > $lastMessage->updatedAt->getTimestamp(); // @phpstan-ignore method.nonObject (property hook guarantees non-null)
    }
}
