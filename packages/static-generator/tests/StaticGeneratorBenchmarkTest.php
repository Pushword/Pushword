<?php

namespace Pushword\StaticGenerator;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use FilesystemIterator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Tests\Perf\QueryCountingTrait;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/** Runs the real static generator and counts the HTML files it writes. */
#[Group('benchmark')]
final class StaticGeneratorBenchmarkTest extends KernelTestCase
{
    use QueryCountingTrait;

    private const string HOST = 'localhost.dev';

    private ?string $isolatedStaticDir = null;

    private ?EntityManagerInterface $em = null;

    private ?string $slugPrefix = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Restore pristine DB: other tests in the same ParaTest worker may have
        // deleted fixture media, causing page rendering to fail on missing media.
        $cacheFile = getenv('PUSHWORD_TEST_DB_CACHE_FILE');
        $dbUrl = getenv('PUSHWORD_TEST_DATABASE_URL');
        if (false !== $cacheFile && '' !== $cacheFile && false !== $dbUrl && str_starts_with($dbUrl, 'sqlite:') && file_exists($cacheFile)) {
            $dbPath = preg_replace('#^sqlite:///+#', '/', $dbUrl);
            if (null !== $dbPath && file_exists($dbPath)) {
                copy($cacheFile, $dbPath);
            }
        }
    }

    protected function tearDown(): void
    {
        $this->stopCountingQueries();
        $this->removeSeededPages();
        if (null !== $this->isolatedStaticDir) {
            new Filesystem()->remove($this->isolatedStaticDir);
        }

        parent::tearDown();
    }

    /** @return iterable<string, array{int}> */
    public static function pipelineVolumes(): iterable
    {
        $configured = getenv('PUSHWORD_BENCH_PIPELINE_VOLUMES') ?: '100,1000';
        $volumes = array_values(array_unique(array_filter(
            array_map(static fn (string $value): int => (int) trim($value), explode(',', $configured)),
            static fn (int $value): bool => $value > 0,
        )));
        sort($volumes);

        foreach ($volumes as $volume) {
            yield number_format($volume, thousands_separator: ' ').' pages' => [$volume];
        }
    }

    #[DataProvider('pipelineVolumes')]
    public function testStaticGenerationPipeline(int $volume): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;

        $this->isolatedStaticDir = sys_get_temp_dir().'/pushword-static-bench-'.getmypid().'-'.$volume;

        $siteConfig = $container->get(SiteRegistry::class)->switchSite(self::HOST)->get();
        $siteConfig->setCustomProperty('cache', 'none');
        $siteConfig->setCustomProperty('static_dir', $this->isolatedStaticDir);
        $this->removeProcessArtifacts();
        $this->seedPages($volume);

        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:static'));
        $this->startCountingQueries($em->getConnection());
        memory_reset_peak_usage();
        $memoryBefore = memory_get_usage(true);
        $status = Command::FAILURE;
        $queryCount = $this->countQueries(static function () use ($commandTester, &$status): void {
            $status = $commandTester->execute([
                'host' => self::HOST,
                '--workers' => 1,
                '--format' => 'agent',
            ]);
        });
        $memoryPeak = memory_get_peak_usage(true);

        self::assertSame(Command::SUCCESS, $status, $commandTester->getDisplay());
        $summary = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($summary);
        self::assertSame('passed', $summary['result'] ?? null, $commandTester->getDisplay());
        $durationMs = $summary['duration_ms'] ?? null;
        self::assertIsInt($durationMs);

        [$pageCount, $syntheticPageCount, $htmlBytes] = $this->measureGeneratedHtmlFiles();
        self::assertGreaterThanOrEqual($volume, $pageCount);
        self::assertSame($volume, $syntheticPageCount);
        $result = [
            'benchmark' => 'database-pipeline',
            'scenario' => 'static',
            'platform' => $this->platformName(),
            'volume' => $volume,
            'duration_ms' => $durationMs,
            'operations' => $pageCount,
            'operation' => 'pages',
            'operations_per_second' => $durationMs > 0 ? round($pageCount * 1000 / $durationMs, 2) : 0.0,
            'queries' => $queryCount,
            'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            'memory_delta_mb' => round(($memoryPeak - $memoryBefore) / 1024 / 1024, 2),
            'html_mb' => round($htmlBytes / 1024 / 1024, 2),
            'timings_ms' => $this->timingBreakdown(),
        ];

        fwrite(\STDERR, '[DATABASE_PIPELINE_BENCHMARK] '.json_encode($result, \JSON_THROW_ON_ERROR)."\n");
    }

    private function seedPages(int $volume): void
    {
        $em = $this->em ?? throw new LogicException('Entity manager not initialized.');
        $this->slugPrefix = 'pipeline-static-'.$volume.'-';
        $content = str_repeat('Synthetic static editorial content. ', 30);

        self::getContainer()->get(PageCacheSuppressor::class)->suppress(function () use ($em, $volume, $content): void {
            for ($i = 0; $i < $volume; ++$i) {
                $next = ($i + 1) % $volume;
                $page = new Page();
                $page->host = self::HOST;
                $page->locale = 'en';
                $page->slug = $this->slugPrefix.$i;
                $page->h1 = 'Synthetic static page '.$i;
                $page->title = 'Synthetic static page '.$i;
                $page->mainContent = '<p>'.$content.'</p><p><a href="/'.$this->slugPrefix.$next.'">Related page</a></p>';
                $page->publishedAt = new DateTime('-'.($i % 365).' days');
                $page->setTags(['content:article', 'topic-'.($i % 12)]);
                $em->persist($page);

                if (0 === ($i + 1) % 250) {
                    $em->flush();
                    $em->clear();
                }
            }

            $em->flush();
            $em->clear();
        });
    }

    /** @return array{int, int, int} total files, synthetic files and their combined bytes */
    private function measureGeneratedHtmlFiles(): array
    {
        $staticDir = $this->isolatedStaticDir ?? throw new LogicException('isolatedStaticDir not set');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($staticDir, FilesystemIterator::SKIP_DOTS),
        );
        $pageCount = 0;
        $syntheticPageCount = 0;
        $htmlBytes = 0;
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && 'html' === $file->getExtension()) {
                ++$pageCount;
                $htmlBytes += $file->getSize();
                if (str_starts_with($file->getBasename(), $this->slugPrefix ?? '')) {
                    ++$syntheticPageCount;
                }
            }
        }

        return [$pageCount, $syntheticPageCount, $htmlBytes];
    }

    /** @return array<string, float|int> */
    private function timingBreakdown(): array
    {
        $stopwatch = self::getContainer()->get(StaticAppGenerator::class)->getStopwatch();
        if (null === $stopwatch) {
            return [];
        }

        $timings = [];
        foreach ($stopwatch->getSections() as $section) {
            foreach ($section->getEvents() as $name => $event) {
                if (! \in_array($name, ['kernel.handle', 'html.compress', 'file.write', 'generatePage'], true)) {
                    continue;
                }

                $timings[$name] = ($timings[$name] ?? 0) + $event->getDuration();
            }
        }

        ksort($timings);

        return $timings;
    }

    private function removeSeededPages(): void
    {
        if (! $this->em instanceof EntityManagerInterface || null === $this->slugPrefix) {
            return;
        }

        $this->em->clear();
        $this->em->createQueryBuilder()
            ->delete(Page::class, 'p')
            ->where('p.host = :host')
            ->andWhere('p.slug LIKE :prefix')
            ->setParameter('host', self::HOST)
            ->setParameter('prefix', $this->slugPrefix.'%')
            ->getQuery()
            ->execute();
        $this->slugPrefix = null;
    }

    private function removeProcessArtifacts(): void
    {
        $varDir = (string) getenv('PUSHWORD_TEST_VAR_DIR');
        if ('' !== $varDir) {
            new Filesystem()->remove(glob($varDir.'/static-generator*.pid') ?: []);
        }
    }

    private function platformName(): string
    {
        $em = $this->em ?? throw new LogicException('Entity manager not initialized.');
        $platform = $em->getConnection()->getDatabasePlatform();

        return match (true) {
            $platform instanceof SQLitePlatform => 'sqlite',
            $platform instanceof PostgreSQLPlatform => 'postgresql',
            $platform instanceof MariaDBPlatform => 'mariadb',
            $platform instanceof AbstractMySQLPlatform => 'mysql',
            default => $platform::class,
        };
    }
}
