<?php

namespace Pushword\Search\EventSubscriber;

use Pushword\Search\Service\Indexer;
use Pushword\Search\Service\IndexManager;
use Pushword\StaticGenerator\Event\StaticPostGenerateEvent;

use function Safe\json_encode;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Builds the per-host index as part of `pw:static` (opt-out via config) and
 * ships it into the static output:
 *  - the SQLite Loupe index, for a PHP/FrankenPHP endpoint at the edge, and/or
 *  - a client-side `search.json` fallback for zero-PHP deployments.
 */
final readonly class StaticSearchSubscriber implements EventSubscriberInterface
{
    private const int JSON_CONTENT_LENGTH = 1000;

    public function __construct(
        private Indexer $indexer,
        private IndexManager $indexManager,
        private Filesystem $filesystem,
        private bool $indexOnStatic,
        private string $staticMode,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [StaticPostGenerateEvent::class => 'onPostGenerate'];
    }

    public function onPostGenerate(StaticPostGenerateEvent $event): void
    {
        if (! $this->indexOnStatic || [] !== $event->errors) {
            return;
        }

        $host = $event->app->getMainHost();
        $documents = $this->indexer->buildDocumentsForHost($host);
        $this->indexManager->replaceAll($host, $documents);

        if (\in_array($this->staticMode, ['endpoint', 'both'], true)) {
            $this->filesystem->copy(
                $this->indexManager->getIndexFile($host),
                $event->staticDir.'/search/loupe.db',
                true,
            );
        }

        if (\in_array($this->staticMode, ['json', 'both'], true)) {
            $this->writeSearchJson($event->staticDir, $documents);
        }
    }

    /**
     * @param list<array<string, mixed>> $documents
     */
    private function writeSearchJson(string $staticDir, array $documents): void
    {
        $entries = array_map(static fn (array $doc): array => [
            'title' => $doc['title'],
            'h1' => $doc['h1'],
            'url' => $doc['url'],
            'slug' => $doc['slug'],
            'tags' => $doc['tags'],
            'content' => mb_substr((string) $doc['content'], 0, self::JSON_CONTENT_LENGTH),
        ], $documents);

        $this->filesystem->dumpFile($staticDir.'/search.json', json_encode($entries));
    }
}
