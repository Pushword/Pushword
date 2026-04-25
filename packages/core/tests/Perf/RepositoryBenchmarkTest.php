<?php

namespace Pushword\Core\Tests\Perf;

use DateTime;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Opt-in benchmark covering PageRepository::warmupSlugCacheLight and
 * MediaRepository::warmupFileNameIndexLight under a realistic dataset.
 * Excluded from the default suite via the `benchmark` group.
 *
 * Run with:
 *   vendor/bin/phpunit --group benchmark \
 *     packages/core/tests/Perf/RepositoryBenchmarkTest.php
 */
#[Group('benchmark')]
final class RepositoryBenchmarkTest extends KernelTestCase
{
    private const int PAGE_COUNT = 1000;

    private const int MEDIA_COUNT = 500;

    private const int HAS_SLUG_ITERATIONS = 10000;

    private const int FIND_MEDIA_ITERATIONS = 1000;

    private EntityManager $em;

    private PageRepository $pageRepo;

    private MediaRepository $mediaRepo;

    /** @var list<string> */
    private array $touchedFiles = [];

    private bool $txOpen = false;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        $this->pageRepo = $em->getRepository(Page::class);
        $this->mediaRepo = $em->getRepository(Media::class);
    }

    protected function tearDown(): void
    {
        if ($this->txOpen && $this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
        }

        $this->txOpen = false;
        foreach ($this->touchedFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->touchedFiles = [];
        parent::tearDown();
    }

    public function testBenchmark(): void
    {
        $homepage = $this->pageRepo->findOneBy(['slug' => 'homepage'])
            ?? throw new RuntimeException('fixture homepage missing');
        $host = $homepage->host;

        $this->em->beginTransaction();
        $this->txOpen = true;

        $this->seedPages($host, self::PAGE_COUNT);
        $this->seedMedias(self::MEDIA_COUNT);

        // Reset repo-level caches + cache.app-backed media index so the warmup
        // we're measuring actually runs fresh DQL, not a cache hit.
        $this->em->clear();
        $this->pageRepo->onClear();
        $this->mediaRepo->bumpVersion();

        // --- Page warmup + hasSlug loop -----------------------------------
        $memBeforePage = memory_get_usage(true);
        $startPage = microtime(true);
        $this->pageRepo->warmupSlugCacheLight($host);
        $pageWarmupMs = (microtime(true) - $startPage) * 1000.0;

        $startHasSlug = microtime(true);
        for ($i = 0; $i < self::HAS_SLUG_ITERATIONS; ++$i) {
            $this->pageRepo->hasSlug('perf-page-'.($i % self::PAGE_COUNT), $host);
        }

        $hasSlugMs = (microtime(true) - $startHasSlug) * 1000.0;
        $memPagePeak = memory_get_peak_usage(true);

        // --- Media warmup + findOneByFileName loop ------------------------
        $memBeforeMedia = memory_get_usage(true);
        $startMedia = microtime(true);
        $this->mediaRepo->warmupFileNameIndexLight();
        $mediaWarmupMs = (microtime(true) - $startMedia) * 1000.0;

        $startFind = microtime(true);
        $found = 0;
        for ($i = 0; $i < self::FIND_MEDIA_ITERATIONS; ++$i) {
            $media = $this->mediaRepo->findOneByFileName('perf-'.($i % self::MEDIA_COUNT).'.png');
            if (null !== $media) {
                ++$found;
            }
        }

        $findMs = (microtime(true) - $startFind) * 1000.0;
        $memMediaPeak = memory_get_peak_usage(true);

        fwrite(\STDERR, \sprintf(
            "\n[BENCHMARK] PageRepository light cache (%d pages)\n"
            ."[BENCHMARK]   warmupSlugCacheLight: %.2f ms\n"
            ."[BENCHMARK]   %d hasSlug() calls:   %.2f ms (%.3f µs/call)\n"
            ."[BENCHMARK]   memory: %.1f MB peak, %.1f MB delta\n",
            self::PAGE_COUNT,
            $pageWarmupMs,
            self::HAS_SLUG_ITERATIONS,
            $hasSlugMs,
            ($hasSlugMs * 1000.0) / self::HAS_SLUG_ITERATIONS,
            $memPagePeak / 1024 / 1024,
            ($memPagePeak - $memBeforePage) / 1024 / 1024,
        ));

        fwrite(\STDERR, \sprintf(
            "[BENCHMARK] MediaRepository filename index (%d medias)\n"
            ."[BENCHMARK]   warmupFileNameIndexLight: %.2f ms\n"
            ."[BENCHMARK]   %d findOneByFileName() calls: %.2f ms (%.3f µs/call) — %d hits\n"
            ."[BENCHMARK]   memory: %.1f MB peak, %.1f MB delta\n",
            self::MEDIA_COUNT,
            $mediaWarmupMs,
            self::FIND_MEDIA_ITERATIONS,
            $findMs,
            ($findMs * 1000.0) / self::FIND_MEDIA_ITERATIONS,
            $found,
            $memMediaPeak / 1024 / 1024,
            ($memMediaPeak - $memBeforeMedia) / 1024 / 1024,
        ));

        // Loose sanity caps — the point is visibility, not gating. Bump only
        // if actual hardware can't keep up, not to silence a regression.
        self::assertLessThan(2000.0, $pageWarmupMs, 'page warmup should be well under 2s');
        self::assertLessThan(1000.0, $hasSlugMs, '10k hasSlug() should be well under 1s');
        self::assertLessThan(2000.0, $mediaWarmupMs, 'media index warmup should be well under 2s');
        self::assertLessThan(2000.0, $findMs, '1k findOneByFileName() should be well under 2s');
        self::assertSame(self::FIND_MEDIA_ITERATIONS, $found, 'every seeded media should be resolvable');
    }

    private function seedPages(string $host, int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $page = new Page();
            $page->setH1('Bench '.$i);
            $page->setSlug('perf-page-'.$i);
            $page->host = $host;
            $page->locale = 'en';
            $page->createdAt = new DateTime();
            // Make ~5% of pages redirects to exercise the redirect map.
            if (0 === $i % 20) {
                $page->setMainContent('Location: /perf-page-'.(($i + 1) % $count));
            } else {
                $page->setMainContent('bench content '.$i);
            }

            $this->em->persist($page);

            if (0 === $i % 200) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $this->em->clear();
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

            if (0 === $i % 100) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $this->em->clear();
    }

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
}
