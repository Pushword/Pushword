<?php

namespace Pushword\PageScanner\Tests\Perf;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\Tests\Perf\QueryCountingTrait;
use Pushword\PageScanner\Service\LinkGraphStorage;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/** Runs the real page scanner over a deterministic internal-link graph. */
#[Group('benchmark')]
final class PageScannerDatabaseBenchmarkTest extends KernelTestCase
{
    use QueryCountingTrait;

    private const string HOST = 'localhost.dev';

    private EntityManagerInterface $em;

    private ?string $slugPrefix = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->stopCountingQueries();
        $this->removeSeededPages();
        $this->removeScannerArtifacts();
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
    public function testPageScanPipeline(int $volume): void
    {
        $this->seedPages($volume);
        $this->em->clear();
        $this->startCountingQueries($this->em->getConnection());

        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:page-scan'));

        memory_reset_peak_usage();
        $memoryBefore = memory_get_usage(true);
        $status = Command::FAILURE;
        $queryCount = $this->countQueries(static function () use ($commandTester, $volume, &$status): void {
            $status = $commandTester->execute([
                'host' => self::HOST,
                '--skip-external' => true,
                '--limit' => max(1000, $volume * 10),
                '--format' => 'agent',
            ]);
        });
        $memoryPeak = memory_get_peak_usage(true);

        self::assertSame(Command::SUCCESS, $status, $commandTester->getDisplay());
        $summary = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($summary);
        self::assertSame('pw:page-scan', $summary['tool'] ?? null);
        $pagesScanned = $summary['pages_scanned'] ?? null;
        $durationMs = $summary['duration_ms'] ?? null;
        self::assertIsInt($pagesScanned);
        self::assertIsInt($durationMs);
        self::assertGreaterThanOrEqual($volume, $pagesScanned);
        $graph = self::getContainer()->get(LinkGraphStorage::class)->read(self::HOST);
        self::assertNotNull($graph);
        $syntheticNodes = array_filter(
            $graph['nodes'],
            fn (string $node): bool => str_starts_with($node, self::HOST.'/'.$this->slugPrefix),
        );
        $syntheticEdges = array_filter(
            $graph['edges'],
            fn (string $node): bool => str_starts_with($node, self::HOST.'/'.$this->slugPrefix),
            \ARRAY_FILTER_USE_KEY,
        );
        self::assertCount($volume, $syntheticNodes);
        self::assertCount($volume, $syntheticEdges);
        $issueCodes = [];
        $issues = $summary['issues'] ?? null;
        self::assertIsArray($issues);
        foreach ($issues as $issue) {
            if (! \is_array($issue)) {
                continue;
            }

            $errors = $issue['errors'] ?? null;
            if (! \is_array($errors)) {
                continue;
            }

            foreach ($errors as $error) {
                if (\is_array($error) && \is_string($error['code'] ?? null)) {
                    $issueCodes[$error['code']] = true;
                }
            }
        }

        $result = [
            'benchmark' => 'database-pipeline',
            'scenario' => 'page-scan',
            'platform' => $this->platformName(),
            'volume' => $volume,
            'duration_ms' => $durationMs,
            'operations' => $pagesScanned,
            'operation' => 'pages',
            'operations_per_second' => $durationMs > 0 ? round($pagesScanned * 1000 / $durationMs, 2) : 0.0,
            'queries' => $queryCount,
            'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            'memory_delta_mb' => round(($memoryPeak - $memoryBefore) / 1024 / 1024, 2),
            'errors' => $summary['errors'] ?? null,
            'issue_codes' => array_keys($issueCodes),
            'graph_edges' => \count($syntheticEdges),
        ];

        fwrite(\STDERR, '[DATABASE_PIPELINE_BENCHMARK] '.json_encode($result, \JSON_THROW_ON_ERROR)."\n");
    }

    private function seedPages(int $volume): void
    {
        $this->slugPrefix = 'pipeline-scan-'.$volume.'-';
        $content = str_repeat('Synthetic linked editorial content. ', 30);

        self::getContainer()->get(PageCacheSuppressor::class)->suppress(function () use ($volume, $content): void {
            for ($i = 0; $i < $volume; ++$i) {
                $next = ($i + 1) % $volume;
                $page = new Page();
                $page->host = self::HOST;
                $page->locale = 'en';
                $page->slug = $this->slugPrefix.$i;
                $page->h1 = 'Synthetic scan page '.$i;
                $page->title = 'Synthetic scan page '.$i;
                $page->mainContent = '<p>'.$content.'</p><p><a href="/'.$this->slugPrefix.$next.'">Related page</a></p>';
                $page->publishedAt = new DateTime('-'.($i % 365).' days');
                $page->setTags(['content:article', 'topic-'.($i % 12)]);
                // The shared test theme references fixture derivatives that are
                // intentionally absent. Keep exercising those checks without
                // serializing one known environment finding per synthetic page.
                $page->setCustomProperty('pageScanErrorsToIgnore', ['image-derivative-broken', 'link-not-found']);
                $this->em->persist($page);

                if (0 === ($i + 1) % 250) {
                    $this->em->flush();
                    $this->em->clear();
                }
            }

            $this->em->flush();
            $this->em->clear();
        });
    }

    private function removeSeededPages(): void
    {
        if (null === $this->slugPrefix) {
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

    private function removeScannerArtifacts(): void
    {
        $varDir = (string) getenv('PUSHWORD_TEST_VAR_DIR');
        if ('' !== $varDir) {
            new Filesystem()->remove(glob($varDir.'/page-scan*') ?: []);
        }
    }

    private function platformName(): string
    {
        $platform = $this->em->getConnection()->getDatabasePlatform();

        return match (true) {
            $platform instanceof SQLitePlatform => 'sqlite',
            $platform instanceof PostgreSQLPlatform => 'postgresql',
            $platform instanceof MariaDBPlatform => 'mariadb',
            $platform instanceof AbstractMySQLPlatform => 'mysql',
            default => $platform::class,
        };
    }
}
