<?php

namespace Pushword\Conversation\Flat;

use Pushword\Core\Component\App\AppConfig;
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

        $csvPath = $this->buildCsvPath($app);

        if (! file_exists($csvPath)) {
            return false;
        }

        // In global mode, check last message across all hosts
        $lastMessage = $this->isGlobalMode()
            ? $this->importer->getLastUpdatedMessage(null)
            : $this->importer->getLastUpdatedMessage($app->getMainHost());

        if (null === $lastMessage) {
            return true;
        }

        $lastUpdatedAt = $lastMessage->updatedAt;

        return filemtime($csvPath) > $lastUpdatedAt->getTimestamp(); // @phpstan-ignore method.nonObject (property hook guarantees non-null)
    }

    private function isGlobalMode(): bool
    {
        return (bool) $this->apps->get()->get('flat_conversation_global');
    }

    private function buildCsvPath(AppConfig $app): string
    {
        if ($this->isGlobalMode()) {
            return $this->contentDirFinder->getBaseDir().'/conversation.csv';
        }

        return $this->contentDirFinder->get($app->getMainHost()).'/conversation.csv';
    }
}
