<?php

namespace Pushword\Core\Tests\Perf;

use DateTime;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\AbstractLogger;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\RouterTwigExtension;
use ReflectionProperty;
use Stringable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Structural regression guards for the PageRepository light cache and
 * MediaRepository filename index hot paths. Counts SQL statements via a DBAL
 * logging middleware wrapped around the live connection. Assertions here fail
 * if a refactor reintroduces N+1 queries, full entity hydration, or per-entry
 * payload growth — correctness tests elsewhere would still pass in those cases.
 */
#[Group('integration')]
final class PerformanceRegressionTest extends KernelTestCase
{
    private EntityManager $em;

    private PageRepository $pageRepo;

    private MediaRepository $mediaRepo;

    private PerfQueryCounter $counter;

    private DriverInterface $originalDriver;

    /** @var list<string> */
    private array $touchedFiles = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        $this->pageRepo = $em->getRepository(Page::class);
        $this->mediaRepo = $em->getRepository(Media::class);

        $this->counter = new PerfQueryCounter();

        // Wrap the live DBAL driver with the logging middleware so every
        // subsequent statement lands in our counter. The existing Connection
        // instance is reused (by Doctrine and by the repositories); we replace
        // its `driver` via reflection and close() so the next connect() goes
        // through the wrapped driver.
        $conn = $this->em->getConnection();
        $driverProp = new ReflectionProperty($conn, 'driver');
        /** @var DriverInterface $original */
        $original = $driverProp->getValue($conn);
        $this->originalDriver = $original;

        $wrapped = new LoggingMiddleware($this->counter)->wrap($original);
        $driverProp->setValue($conn, $wrapped);
        $conn->close();
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $conn = $this->em->getConnection();
            $driverProp = new ReflectionProperty($conn, 'driver');
            $driverProp->setValue($conn, $this->originalDriver);
            $conn->close();
        }

        foreach ($this->touchedFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->touchedFiles = [];

        parent::tearDown();
    }

    /**
     * Create an empty file at the location MediaHashListener::prePersist()
     * will sha1_file() on flush. Registered for cleanup in tearDown().
     */
    private function touchMediaFile(string $fileName): void
    {
        $mediaDir = (string) getenv('PUSHWORD_TEST_MEDIA_DIR');
        if ('' === $mediaDir) {
            $mediaDir = \dirname(__DIR__, 3).'/skeleton/media';
        }

        $path = $mediaDir.'/'.$fileName;
        if (! is_file($path)) {
            file_put_contents($path, '');
            $this->touchedFiles[] = $path;
        }
    }

    /**
     * Execute $fn and return the number of SQL statements it issued.
     */
    private function countQueries(callable $fn): int
    {
        $before = $this->counter->count;
        $fn();

        return $this->counter->count - $before;
    }

    private function hostForFixtures(): string
    {
        $homepage = $this->pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($homepage, 'fixture page with slug=homepage is required');

        return $homepage->host;
    }

    private function resetRepoCaches(): void
    {
        $this->em->clear();
        $this->pageRepo->onClear();
        // bumpVersion() invalidates the cache.app-persisted filename index
        // (not just the repo-level state), forcing the next warmup to run a
        // real DQL query. Without this, a cached index from a previous test
        // or a prior run would make the query-count assertions meaningless.
        $this->mediaRepo->bumpVersion();
    }

    public function testWarmupSlugCacheLightEmitsExactlyOneQuery(): void
    {
        $host = $this->hostForFixtures();
        $this->resetRepoCaches();

        $before = $this->counter->count;
        $this->pageRepo->warmupSlugCacheLight($host);
        $issued = $this->counter->count - $before;

        self::assertSame(1, $issued, 'warmupSlugCacheLight must execute a single SELECT');

        $lastSql = [] === $this->counter->queries ? '' : $this->counter->queries[\count($this->counter->queries) - 1];
        self::assertStringNotContainsStringIgnoringCase(
            'JOIN',
            $lastSql,
            'light warmup must stay on a flat scalar fetch — no joins',
        );
    }

    public function testHasSlugAfterWarmupEmitsZeroQueries(): void
    {
        $host = $this->hostForFixtures();
        $this->resetRepoCaches();
        $this->pageRepo->warmupSlugCacheLight($host);

        $count = $this->countQueries(function () use ($host): void {
            for ($i = 0; $i < 50; ++$i) {
                $this->pageRepo->hasSlug('homepage', $host);
                $this->pageRepo->hasSlug('never-exists-'.$i, $host);
            }
        });

        self::assertSame(0, $count, 'hasSlug() must not hit the DB after warmup');
    }

    public function testGetRedirectForAfterWarmupEmitsZeroQueries(): void
    {
        $host = $this->hostForFixtures();
        $this->resetRepoCaches();
        $this->pageRepo->warmupSlugCacheLight($host);

        $count = $this->countQueries(function () use ($host): void {
            for ($i = 0; $i < 50; ++$i) {
                $this->pageRepo->getRedirectFor('pushword', $host);
                $this->pageRepo->getRedirectFor('never-exists-'.$i, $host);
            }
        });

        self::assertSame(0, $count, 'getRedirectFor() must not hit the DB after warmup');
    }

    public function testRouterTwigPageFunctionEmitsSingleQueryPerHost(): void
    {
        $host = $this->hostForFixtures();
        $this->resetRepoCaches();

        /** @var RouterTwigExtension $twig */
        $twig = self::getContainer()->get(RouterTwigExtension::class);

        $count = $this->countQueries(static function () use ($twig, $host): void {
            for ($i = 0; $i < 20; ++$i) {
                $twig->getPageUri('homepage', $host);
            }
        });

        self::assertSame(1, $count, 'page() Twig function must warm the light cache exactly once per host');
    }

    public function testWarmupFileNameIndexLightEmitsExactlyOneQuery(): void
    {
        $this->resetRepoCaches();

        $before = \count($this->counter->queries);
        $this->mediaRepo->warmupFileNameIndexLight();
        $issuedSql = \array_slice($this->counter->queries, $before);

        self::assertCount(
            1,
            $issuedSql,
            'warmupFileNameIndexLight must execute a single SELECT — saw '.json_encode($issuedSql),
        );

        $lastSql = [] === $this->counter->queries ? '' : $this->counter->queries[\count($this->counter->queries) - 1];
        self::assertStringNotContainsStringIgnoringCase(
            'JOIN',
            $lastSql,
            'filename index warmup must stay on a scalar projection — no joins',
        );
    }

    public function testFindOneByFileNameAfterWarmupEmitsSingleFindQuery(): void
    {
        $this->resetRepoCaches();
        $this->mediaRepo->warmupFileNameIndexLight();

        $count = $this->countQueries(fn (): ?Media => $this->mediaRepo->findOneByFileName('1.jpg'));

        self::assertSame(1, $count, 'findOneByFileName must emit exactly one PK find() query');
    }

    public function testFindOneByFileNameOrHistoryDoesNotDoLikeQuery(): void
    {
        $this->touchMediaFile('history-probe-current.png');

        $this->em->beginTransaction();

        try {
            $media = new Media();
            $media->setProjectDir(sys_get_temp_dir())
                ->setStoreIn(sys_get_temp_dir())
                ->setMimeType('image/png')
                ->setSize(1)
                ->setDimensions([10, 10])
                ->setFileName('history-probe-current.png')
                ->setAlt('history-probe')
                ->setFileNameHistory(['history-probe-legacy.png']);
            $this->em->persist($media);
            $this->em->flush();

            $this->resetRepoCaches();
            $this->mediaRepo->warmupFileNameIndexLight();

            $before = $this->counter->count;
            $sliceStart = \count($this->counter->queries);
            $result = $this->mediaRepo->findOneByFileNameOrHistory('history-probe-legacy.png');

            self::assertNotNull($result, 'history fallback must resolve the media');
            self::assertSame('history-probe-current.png', $result->getFileName());

            $issuedSql = \array_slice($this->counter->queries, $sliceStart);
            foreach ($issuedSql as $sql) {
                self::assertStringNotContainsStringIgnoringCase(
                    'LIKE',
                    $sql,
                    'history fallback must stay in-memory — no LIKE query on the DB',
                );
            }

            self::assertLessThanOrEqual(
                1,
                $this->counter->count - $before,
                'history fallback should at most do a single find() by PK',
            );
        } finally {
            $this->em->rollback();
        }
    }

    public function testLightCachePayloadStaysWithinBudget(): void
    {
        $host = $this->hostForFixtures();

        $this->em->beginTransaction();

        try {
            $this->seedPages($host, 500);
            $this->resetRepoCaches();
            $this->pageRepo->warmupSlugCacheLight($host);

            [$slugSet, $redirects] = $this->readLightCacheState($host);
            $entries = \count($slugSet);
            self::assertGreaterThanOrEqual(500, $entries);

            $payloadSize = \strlen(serialize($slugSet)) + \strlen(serialize($redirects));
            $budget = 50 * $entries;

            self::assertLessThan(
                $budget,
                $payloadSize,
                \sprintf(
                    'light cache payload %d bytes exceeds budget %d for %d entries (regression: entities hydrated instead of scalars?)',
                    $payloadSize,
                    $budget,
                    $entries,
                ),
            );
        } finally {
            $this->em->rollback();
        }
    }

    public function testFileNameIndexPayloadStaysWithinBudget(): void
    {
        $this->em->beginTransaction();

        try {
            $this->seedMedias(300);
            $this->resetRepoCaches();
            $this->mediaRepo->warmupFileNameIndexLight();

            $index = $this->readFileNameIndex();
            $entries = \count($index);
            self::assertGreaterThanOrEqual(300, $entries);

            $payloadSize = \strlen(serialize($index));
            $budget = 120 * $entries;

            self::assertLessThan(
                $budget,
                $payloadSize,
                \sprintf(
                    'filename index payload %d bytes exceeds budget %d for %d entries',
                    $payloadSize,
                    $budget,
                    $entries,
                ),
            );
        } finally {
            $this->em->rollback();
        }
    }

    private function seedPages(string $host, int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $page = new Page();
            $page->setH1('Perf '.$i);
            $page->setSlug('perf-page-'.$i);
            $page->host = $host;
            $page->locale = 'en';
            $page->createdAt = new DateTime();
            $page->setMainContent('perf content '.$i);
            $this->em->persist($page);
        }

        $this->em->flush();
    }

    private function seedMedias(int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $fileName = 'perf-'.$i.'.png';
            $this->touchMediaFile($fileName);

            $media = new Media();
            $media->setProjectDir(sys_get_temp_dir())
                ->setStoreIn(sys_get_temp_dir())
                ->setMimeType('image/png')
                ->setSize(1)
                ->setDimensions([10, 10])
                ->setFileName($fileName)
                ->setAlt('p'.$i);
            $this->em->persist($media);
        }

        $this->em->flush();
    }

    /**
     * @return array{0: array<string, true>, 1: array<string, array{url: string, code: int}>}
     */
    private function readLightCacheState(string $host): array
    {
        $slugSetsProp = new ReflectionProperty(PageRepository::class, 'slugSets');
        $redirectMapsProp = new ReflectionProperty(PageRepository::class, 'redirectMaps');

        /** @var array<string, array<string, true>> $slugSets */
        $slugSets = $slugSetsProp->getValue($this->pageRepo);
        /** @var array<string, array<string, array{url: string, code: int}>> $redirectMaps */
        $redirectMaps = $redirectMapsProp->getValue($this->pageRepo);

        return [$slugSets[$host] ?? [], $redirectMaps[$host] ?? []];
    }

    /**
     * @return array<string, array{id: int, fileName: string, fileNameHistory: list<string>}>
     */
    private function readFileNameIndex(): array
    {
        $prop = new ReflectionProperty(MediaRepository::class, 'fileNameIndexLight');
        /** @var array<string, array{id: int, fileName: string, fileNameHistory: list<string>}>|null $value */
        $value = $prop->getValue($this->mediaRepo);

        return $value ?? [];
    }
}

/**
 * PSR logger that counts DBAL "Executing statement" log messages.
 * Kept file-local — not promoted to a shared utility until a second test needs it.
 */
final class PerfQueryCounter extends AbstractLogger
{
    public int $count = 0;

    /** @var list<string> */
    public array $queries = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $msg = (string) $message;
        // Match both "Executing statement: {sql}" (from prepared-statement execute
        // and Connection::exec) and "Executing query: {sql}" (from Connection::query).
        if (! str_starts_with($msg, 'Executing statement') && ! str_starts_with($msg, 'Executing query')) {
            return;
        }

        ++$this->count;
        $sql = $context['sql'] ?? $msg;
        $this->queries[] = \is_string($sql) ? $sql : $msg;
    }
}
