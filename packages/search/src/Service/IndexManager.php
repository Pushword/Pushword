<?php

namespace Pushword\Search\Service;

use Loupe\Loupe\Configuration as LoupeConfiguration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactory;
use Pushword\Core\Site\SiteRegistry;

use function Safe\preg_replace;

/**
 * Opens (and lazily creates) one Loupe index per host.
 *
 * Loupe persists each index as a `loupe.db` SQLite file inside a per-host
 * directory, which keeps the index portable for the static workflow.
 */
final class IndexManager
{
    /**
     * Searchable attributes, ordered by descending weight: headings rank above
     * the body, so document structure — not markup — drives ranking.
     *
     * @var list<string>
     */
    public const array SEARCHABLE_ATTRIBUTES = ['title', 'h1', 'tags', 'content'];

    /** @var list<string> */
    public const array FILTERABLE_ATTRIBUTES = ['host', 'locale', 'tags'];

    /** @var array<string, Loupe> */
    private array $indexes = [];

    private readonly LoupeFactory $factory;

    private readonly string $indexDir;

    public function __construct(
        private readonly SiteRegistry $siteRegistry,
        string $indexDir,
    ) {
        // ParaTest isolates the index dir per worker to avoid SQLite races on
        // a shared var/search, mirroring the static generator's cache dir.
        $testVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        $this->indexDir = false !== $testVarDir && '' !== $testVarDir ? $testVarDir.'/search' : $indexDir;

        $this->factory = new LoupeFactory();
    }

    public function getLoupe(string $host): Loupe
    {
        return $this->indexes[$host] ??= $this->factory->create(
            $this->getIndexPath($host),
            $this->buildConfiguration($host),
        );
    }

    /**
     * Rebuild a host index from scratch with the given documents.
     *
     * @param list<array<string, mixed>> $documents
     */
    public function replaceAll(string $host, array $documents): void
    {
        $loupe = $this->getLoupe($host);
        $loupe->deleteAllDocuments();

        if ([] !== $documents) {
            $loupe->addDocuments($documents);
        }
    }

    public function getIndexPath(string $host): string
    {
        return $this->indexDir.'/'.$this->sanitizeHost($host);
    }

    public function getIndexFile(string $host): string
    {
        return $this->getIndexPath($host).'/loupe.db';
    }

    public function exists(string $host): bool
    {
        return is_file($this->getIndexFile($host));
    }

    private function buildConfiguration(string $host): LoupeConfiguration
    {
        return LoupeConfiguration::create()
            ->withPrimaryKey('id')
            ->withSearchableAttributes(self::SEARCHABLE_ATTRIBUTES)
            ->withFilterableAttributes(self::FILTERABLE_ATTRIBUTES)
            ->withLanguages($this->siteRegistry->get($host)->getLocales());
    }

    private function sanitizeHost(string $host): string
    {
        return preg_replace('/[^a-z0-9._-]+/i', '_', $host);
    }
}
