<?php

namespace Pushword\Admin\Tests\Perf;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\Tests\Perf\QueryCountingTrait;
use Symfony\Component\HttpFoundation\Request;

/** Exercises real EasyAdmin list, pagination, search, filter and sort requests. */
#[Group('benchmark')]
final class AdminDatabaseBenchmarkTest extends AbstractAdminTestClass
{
    use QueryCountingTrait;

    private const string HOST = 'localhost.dev';

    private const int REPETITIONS = 3;

    private ?EntityManagerInterface $em = null;

    private ?string $slugPrefix = null;

    protected function tearDown(): void
    {
        $this->stopCountingQueries();
        $this->removeSeededPages();
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
    public function testEasyAdminPipeline(int $volume): void
    {
        $client = $this->loginUser();
        $client->disableReboot();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        $this->seedPages($volume);

        $baseUrl = $this->generateAdminUrl('admin_page_list');
        $urls = [
            'list' => $baseUrl,
            'page' => $this->appendQuery($baseUrl, ['page' => 2]),
            'search' => $this->appendQuery($baseUrl, ['filters' => ['h1' => ['comparison' => 'like', 'value' => 'pipeline admin marker']]]),
            'host_filter' => $this->appendQuery($baseUrl, ['filters' => ['host' => ['comparison' => '=', 'value' => self::HOST]]]),
            'sort' => $this->appendQuery($baseUrl, ['sort' => ['slug' => 'ASC']]),
        ];

        $client->request(Request::METHOD_GET, $baseUrl);
        self::assertResponseIsSuccessful();
        $client->request(Request::METHOD_GET, $urls['search']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Pipeline admin marker', (string) $client->getResponse()->getContent());

        $this->startCountingQueries($em->getConnection());
        memory_reset_peak_usage();
        $memoryBefore = memory_get_usage(true);
        $durations = array_fill_keys(array_keys($urls), []);
        $queryCount = $this->countQueries(static function () use ($client, $urls, &$durations): void {
            for ($repetition = 0; $repetition < self::REPETITIONS; ++$repetition) {
                foreach ($urls as $name => $url) {
                    $start = hrtime(true);
                    $client->request(Request::METHOD_GET, $url);
                    $durations[$name][] = (hrtime(true) - $start) / 1_000_000;
                    self::assertResponseIsSuccessful();
                }
            }
        });
        $memoryPeak = memory_get_peak_usage(true);

        $medians = [];
        foreach ($durations as $name => $values) {
            sort($values);
            $medians[$name] = round($values[intdiv(self::REPETITIONS, 2)], 3);
        }

        $durationMs = array_sum($medians);

        $result = [
            'benchmark' => 'database-pipeline',
            'scenario' => 'easyadmin',
            'platform' => $this->platformName(),
            'volume' => $volume,
            'duration_ms' => round($durationMs, 3),
            'operations' => \count($urls),
            'operation' => 'requests',
            'operations_per_second' => $durationMs > 0 ? round(\count($urls) * 1000 / $durationMs, 2) : 0.0,
            'queries' => intdiv($queryCount, self::REPETITIONS),
            'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            'memory_delta_mb' => round(($memoryPeak - $memoryBefore) / 1024 / 1024, 2),
            'request_ms' => $medians,
        ];

        fwrite(\STDERR, '[DATABASE_PIPELINE_BENCHMARK] '.json_encode($result, \JSON_THROW_ON_ERROR)."\n");
    }

    private function seedPages(int $volume): void
    {
        $em = $this->em ?? throw new LogicException('Entity manager not initialized.');
        $this->slugPrefix = 'pipeline-admin-'.$volume.'-';

        self::getContainer()->get(PageCacheSuppressor::class)->suppress(function () use ($em, $volume): void {
            $batch = [];
            for ($i = 0; $i < $volume; ++$i) {
                $page = new Page();
                $page->host = self::HOST;
                $page->locale = 'en';
                $page->slug = $this->slugPrefix.$i;
                $page->h1 = 'Pipeline admin marker '.$i;
                $page->title = 'Pipeline admin marker '.$i;
                $page->name = 'Pipeline admin marker '.$i;
                $page->mainContent = str_repeat('Synthetic admin content. ', 20);
                $page->publishedAt = new DateTime('-'.($i % 365).' days');
                $page->setTags(['content:article', 'admin-bucket-'.($i % 8)]);
                $em->persist($page);
                $batch[] = $page;

                if (0 === ($i + 1) % 250) {
                    $em->flush();
                    foreach ($batch as $persistedPage) {
                        $em->detach($persistedPage);
                    }

                    $batch = [];
                }
            }

            $em->flush();
            foreach ($batch as $persistedPage) {
                $em->detach($persistedPage);
            }
        });
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

    /** @param array<string, mixed> $parameters */
    private function appendQuery(string $url, array $parameters): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($parameters, encoding_type: \PHP_QUERY_RFC3986);
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
