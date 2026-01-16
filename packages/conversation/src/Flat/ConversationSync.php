<?php

namespace Pushword\Conversation\Flat;

use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\FlatFileContentDirFinder;

use function Safe\filemtime;

final readonly class ConversationSync
{
    public function __construct(
        private AppPool $apps,
        private FlatFileContentDirFinder $contentDirFinder,
        public ConversationImporter $importer,
        public ConversationExporter $exporter,
    ) {
    }

    public function sync(?string $host = null, bool $forceExport = false): void
    {
        if (! $forceExport && $this->mustImport($host)) {
            $this->importer->import($host);

            return;
        }

        $this->exporter->export($host);
    }

    public function mustImport(?string $host = null): bool
    {
        $app = null !== $host
            ? $this->apps->switchCurrentApp($host)->get()
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
