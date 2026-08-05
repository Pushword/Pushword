<?php

namespace Pushword\Search\Service;

use Doctrine\DBAL\Exception\DriverException;
use Loupe\Loupe\Configuration as LoupeConfiguration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactory;
use PDO;
use Psr\Log\LoggerInterface;
use Pushword\Core\Site\SiteRegistry;

use function Safe\preg_replace;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Opens (and lazily creates) one Loupe index per host.
 *
 * Loupe persists each index as a `loupe.db` SQLite file inside a per-host
 * directory, which keeps the index portable for the static workflow.
 */
final class IndexManager
{
    /**
     * SQLite codes meaning the file cannot be read as a database at all:
     * SQLITE_CORRUPT and SQLITE_NOTADB.
     */
    private const array UNREADABLE_INDEX_CODES = [11, 26];

    /** @var array<string, Loupe> */
    private array $indexes = [];

    private readonly LoupeFactory $factory;

    private readonly string $indexDir;

    /**
     * @param list<string> $searchableAttributes ordered by descending weight, so document structure drives ranking
     * @param list<string> $filterableAttributes
     */
    public function __construct(
        private readonly SiteRegistry $siteRegistry,
        string $indexDir,
        private readonly array $searchableAttributes,
        private readonly array $filterableAttributes,
        private readonly ?LoggerInterface $logger = null,
    ) {
        // ParaTest isolates the index dir per worker to avoid SQLite races on
        // a shared var/search, mirroring the static generator's cache dir.
        $testVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        $this->indexDir = false !== $testVarDir && '' !== $testVarDir ? $testVarDir.'/search' : $indexDir;

        $this->factory = new LoupeFactory();
    }

    public function getLoupe(string $host): Loupe
    {
        return $this->indexes[$host] ??= $this->openIndex($host);
    }

    /**
     * Fold the write-ahead log back into `loupe.db`, leaving the file
     * self-contained.
     *
     * Loupe indexes in WAL mode, so right after a write the documents sit in
     * `loupe.db-wal` and the main file still holds none of them. Anything that
     * copies the bare file — the static export — ships an empty index without
     * this.
     */
    public function checkpoint(string $host): void
    {
        if (! $this->exists($host)) {
            return;
        }

        // Loupe keeps its own connection private, and a checkpoint is valid over
        // any connection to the file.
        new PDO('sqlite:'.$this->getIndexFile($host))->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    }

    /**
     * Rebuild a host index from scratch with the given documents.
     *
     * @param list<array<string, mixed>> $documents
     */
    public function replaceAll(string $host, array $documents): void
    {
        try {
            $this->write($host, $documents);
        } catch (DriverException $driverException) {
            if (! $this->isUnreadable($driverException)) {
                throw $driverException;
            }

            // Damage confined to the interior of the file gets past the check in
            // openIndex(): the pages it reads are intact, and SQLite only trips
            // over the torn ones once a statement reaches them. Resetting from
            // here costs nothing — we are replacing every document anyway.
            $this->logger?->warning(
                'Search: the index of {host} was unreadable and has been rebuilt from scratch.',
                ['host' => $host],
            );

            $this->reset($host);
            $this->write($host, $documents);
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

    /**
     * @param list<array<string, mixed>> $documents
     */
    private function write(string $host, array $documents): void
    {
        $loupe = $this->getLoupe($host);
        $loupe->deleteAllDocuments();

        if ([] !== $documents) {
            $loupe->addDocuments($documents);
        }
    }

    /**
     * Loupe deliberately runs SQLite with `synchronous = OFF` — a search index is
     * rebuildable, so it trades durability for write speed. The cost is that a
     * writer killed mid-write leaves `loupe.db` unreadable, and every later open
     * then fails for good, taking the whole static build down with it. Take Loupe
     * up on its own remedy: drop the unusable file and index again.
     */
    private function openIndex(string $host): Loupe
    {
        $loupe = $this->createLoupe($host);

        try {
            // An unreadable file only gives itself away when queried, not when
            // opened. This reads the head of the file, where a truncated write
            // leaves its damage; the interior is only covered once replaceAll()
            // walks it.
            $loupe->needsReindex();

            return $loupe;
        } catch (DriverException $driverException) {
            if (! $this->isUnreadable($driverException)) {
                throw $driverException;
            }

            $this->logger?->warning(
                'Search: the index of {host} was unreadable and has been reset — run pw:search:index to repopulate it.',
                ['host' => $host],
            );

            $this->reset($host);

            return $this->createLoupe($host);
        }
    }

    /** Drop the unusable file, so the next open builds a fresh index. */
    private function reset(string $host): void
    {
        unset($this->indexes[$host]);

        new Filesystem()->remove($this->getIndexPath($host));
    }

    private function isUnreadable(DriverException $driverException): bool
    {
        return \in_array($driverException->getCode(), self::UNREADABLE_INDEX_CODES, true);
    }

    private function createLoupe(string $host): Loupe
    {
        return $this->factory->create($this->getIndexPath($host), $this->buildConfiguration($host));
    }

    private function buildConfiguration(string $host): LoupeConfiguration
    {
        return LoupeConfiguration::create()
            ->withPrimaryKey('id')
            ->withSearchableAttributes($this->searchableAttributes)
            ->withFilterableAttributes($this->filterableAttributes)
            ->withLanguages($this->siteRegistry->get($host)->getLocales());
    }

    private function sanitizeHost(string $host): string
    {
        $sanitized = preg_replace('/[^a-z0-9._-]+/i', '_', $host);
        assert(is_string($sanitized));

        return $sanitized;
    }
}
