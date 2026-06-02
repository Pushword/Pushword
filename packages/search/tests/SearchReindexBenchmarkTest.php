<?php

namespace Pushword\Search\Tests;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Cache\CacheItemPoolInterface;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Search\Service\Indexer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Opt-in throughput + memory benchmark for full host reindex.
 *
 * Reindex renders each indexable page through the EntityFilter pipeline so the
 * indexed text matches what visitors read; the markdown render cache is the
 * single biggest lever for that path. This benchmark measures a cold reindex
 * (cache cleared) versus a warm one (cache primed by the cold run) so the gap
 * stays visible on stderr. Excluded from the default suite via `benchmark`.
 *
 * Run with:
 *   vendor/bin/phpunit --group benchmark \
 *     packages/search/tests/SearchReindexBenchmarkTest.php
 */
#[Group('benchmark')]
final class SearchReindexBenchmarkTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const int PAGE_COUNT = 300;

    private EntityManagerInterface $em;

    private Indexer $indexer;

    private CacheItemPoolInterface $markdownCache;

    private bool $txOpen = false;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->indexer = $container->get(Indexer::class);
        /** @var CacheItemPoolInterface $pool */
        $pool = $container->get('cache.pushword_markdown');
        $this->markdownCache = $pool;
    }

    protected function tearDown(): void
    {
        if ($this->txOpen && $this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
            $this->txOpen = false;

            // Seeded pages are gone from the DB; rebuild the index so it
            // matches the fixture set again instead of holding 300 ghost ids.
            $this->indexer->reindexHost(self::HOST);
        }

        parent::tearDown();
    }

    public function testBenchmark(): void
    {
        $container = self::getContainer();

        $this->em->beginTransaction();
        $this->txOpen = true;

        // PageCacheSuppressor silences both the per-page Open Graph image
        // generation in PageListener and the PageIndexInvalidator entity
        // listener, so seeding does not dispatch 300 Messenger envelopes.
        $container->get(PageCacheSuppressor::class)->suppress(function (): void {
            $this->seedPages(self::PAGE_COUNT);
        });

        // --- Cold reindex: markdown cache cleared so every page body is
        // re-rendered from scratch. -----------------------------------------
        $this->markdownCache->clear();

        $memBeforeCold = memory_get_usage(true);
        $startCold = microtime(true);
        $coldCount = $this->indexer->reindexHost(self::HOST);
        $coldMs = (microtime(true) - $startCold) * 1000.0;
        $memColdPeak = memory_get_peak_usage(true);

        // --- Warm reindex: markdown cache populated by the cold run, so each
        // page body should resolve through the cached fragment. -------------
        $memBeforeWarm = memory_get_usage(true);
        $startWarm = microtime(true);
        $warmCount = $this->indexer->reindexHost(self::HOST);
        $warmMs = (microtime(true) - $startWarm) * 1000.0;
        $memWarmPeak = memory_get_peak_usage(true);

        $coldPps = $coldCount / max($coldMs / 1000.0, 0.001);
        $warmPps = $warmCount / max($warmMs / 1000.0, 0.001);

        fwrite(\STDERR, \sprintf(
            "\n[BENCHMARK] Search reindex (%s, ~%d seeded pages)\n"
            ."[BENCHMARK]   cold: %d pages in %.2f ms (%.1f pages/s) — peak %.1f MB, delta %.1f MB\n"
            ."[BENCHMARK]   warm: %d pages in %.2f ms (%.1f pages/s) — peak %.1f MB, delta %.1f MB\n"
            ."[BENCHMARK]   speedup: %.2fx\n",
            self::HOST,
            self::PAGE_COUNT,
            $coldCount,
            $coldMs,
            $coldPps,
            $memColdPeak / 1024 / 1024,
            ($memColdPeak - $memBeforeCold) / 1024 / 1024,
            $warmCount,
            $warmMs,
            $warmPps,
            $memWarmPeak / 1024 / 1024,
            ($memWarmPeak - $memBeforeWarm) / 1024 / 1024,
            $warmMs > 0.0 ? $coldMs / $warmMs : 0.0,
        ));

        self::assertGreaterThanOrEqual(self::PAGE_COUNT, $coldCount, 'cold reindex must cover every seeded page');
        self::assertSame($coldCount, $warmCount, 'warm reindex must match cold over the same dataset');

        // Loose sanity caps — visibility, not gating. Bump only if real
        // hardware can't keep up, never to silence a regression.
        self::assertLessThan(30000.0, $coldMs, 'cold reindex of 300 pages should finish well under 30s');
        self::assertLessThan($coldMs, $warmMs, 'warm reindex must be faster than cold — markdown cache should pay off');
    }

    private function seedPages(int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $page = new Page();
            $page->setH1('Bench Page '.$i);
            $page->setSlug('search-bench-'.$i);
            $page->host = self::HOST;
            $page->locale = 'en';
            $page->createdAt = new DateTime();
            $page->publishedAt = new DateTime('-1 day');
            // Realistic-ish body: a heading, prose with **bold**, a list and a
            // link — covers the markdown paths the parser actually exercises.
            $page->setMainContent(\sprintf(
                "## Bench %d\n\nSome **bold** prose for page %d, with a [link](/page) and a list:\n\n- one\n- two\n- three\n",
                $i,
                $i,
            ));
            $this->em->persist($page);

            if (0 === $i % 100) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $this->em->clear();
    }
}
