<?php

namespace Pushword\Core\Tests\Perf;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Opt-in comparison of the Doctrine database as the page corpus grows.
 *
 * Run all three engines and print a comparison table with:
 *   composer bench-databases
 *
 * Override the default 100/1,000/10,000 row ladder with:
 *   PUSHWORD_BENCH_VOLUMES=1000,10000 composer bench-databases
 */
#[Group('benchmark')]
final class DatabaseVolumeBenchmarkTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const int INDEXED_LOOKUPS = 200;

    private EntityManagerInterface $em;

    private bool $transactionOpen = false;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->beginTransaction();

        $this->transactionOpen = true;
    }

    protected function tearDown(): void
    {
        if ($this->transactionOpen && $this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
        }

        $this->transactionOpen = false;
        parent::tearDown();
    }

    /** @return iterable<string, array{int}> */
    public static function volumes(): iterable
    {
        $configured = getenv('PUSHWORD_BENCH_VOLUMES') ?: '100,1000,10000';
        $volumes = array_values(array_unique(array_filter(
            array_map(static fn (string $value): int => (int) trim($value), explode(',', $configured)),
            static fn (int $value): bool => $value > 0,
        )));
        sort($volumes);

        foreach ($volumes as $volume) {
            yield number_format($volume, thousands_separator: ' ').' pages' => [$volume];
        }
    }

    #[DataProvider('volumes')]
    public function testDatabaseScaling(int $volume): void
    {
        // Pay one-time ORM metadata, listener and prepared-statement costs before the
        // measured corpus. Otherwise the smallest first data set absorbs all startup
        // work and looks slower than the next, larger one.
        self::getContainer()->get(PageCacheSuppressor::class)->suppress(
            fn () => $this->seedPages(1, 'dbwarmup'),
        );

        $writeStart = hrtime(true);
        self::getContainer()->get(PageCacheSuppressor::class)->suppress(
            fn () => $this->seedPages($volume),
        );
        $writeMs = $this->elapsedMs($writeStart);
        $this->em->clear();

        [$countMs, $count] = $this->median(fn (): int => (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Page::class, 'p')
            ->where('p.host = :host')
            ->andWhere('p.slug LIKE :prefix')
            ->setParameter('host', self::HOST)
            ->setParameter('prefix', 'dbbench-%')
            ->getQuery()
            ->getSingleScalarResult());

        $lookupCount = self::INDEXED_LOOKUPS;
        [$lookupMs, $lookupHits] = $this->median(function () use ($lookupCount, $volume): int {
            $query = $this->em->createQueryBuilder()
                ->select('p.id')
                ->from(Page::class, 'p')
                ->where('p.host = :host')
                ->andWhere('p.slug = :slug')
                ->setParameter('host', self::HOST)
                ->getQuery();
            $hits = 0;
            for ($i = 0; $i < $lookupCount; ++$i) {
                $slugIndex = (int) floor(($i * $volume) / $lookupCount);
                $query->setParameter('slug', 'dbbench-'.$slugIndex);
                $hits += null !== $query->getOneOrNullResult() ? 1 : 0;
            }

            return $hits;
        });

        [$tagMs, $tagCount] = $this->median(fn (): int => (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Page::class, 'p')
            ->where('p.host = :host')
            ->andWhere('p.slug LIKE :prefix')
            ->andWhere('JSON_TEXT(p.tags) LIKE :tag')
            ->setParameter('host', self::HOST)
            ->setParameter('prefix', 'dbbench-%')
            ->setParameter('tag', '%"bench-hot"%')
            ->getQuery()
            ->getSingleScalarResult());

        [$jsonMs, $jsonCount] = $this->median(fn (): int => (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Page::class, 'p')
            ->where('p.host = :host')
            ->andWhere('p.slug LIKE :prefix')
            ->andWhere("JSON_NUMBER(p.customProperties, '$.rank') >= :minimum")
            ->setParameter('host', self::HOST)
            ->setParameter('prefix', 'dbbench-%')
            ->setParameter('minimum', intdiv($volume, 2))
            ->getQuery()
            ->getSingleScalarResult());

        [$listMs, $listCount] = $this->median(fn (): int => \count($this->em->createQueryBuilder()
            ->select('p.id')
            ->from(Page::class, 'p')
            ->where('p.host = :host')
            ->andWhere('p.slug LIKE :prefix')
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults(50)
            ->setParameter('host', self::HOST)
            ->setParameter('prefix', 'dbbench-%')
            ->getQuery()
            ->getScalarResult()));

        self::assertSame($volume, $count);
        self::assertSame($lookupCount, $lookupHits);
        self::assertSame((int) ceil($volume / 2), $tagCount);
        self::assertSame($volume - intdiv($volume, 2), $jsonCount);
        self::assertSame(min(50, $volume), $listCount);

        $platform = $this->platformName();
        $result = [
            'benchmark' => 'database-volume',
            'platform' => $platform,
            'volume' => $volume,
            'write_ms' => round($writeMs, 3),
            'count_ms' => round($countMs, 3),
            'lookup_ms' => round($lookupMs, 3),
            'lookup_queries' => $lookupCount,
            'tag_like_ms' => round($tagMs, 3),
            'json_number_ms' => round($jsonMs, 3),
            'list_ms' => round($listMs, 3),
        ];

        fwrite(\STDERR, '[DATABASE_BENCHMARK] '.json_encode($result, \JSON_THROW_ON_ERROR)."\n");
    }

    private function seedPages(int $volume, string $slugPrefix = 'dbbench'): void
    {
        for ($i = 0; $i < $volume; ++$i) {
            $page = new Page();
            $page->host = self::HOST;
            $page->locale = 'en';
            $page->slug = $slugPrefix.'-'.$i;
            $page->h1 = 'Database benchmark '.$i;
            $page->mainContent = 'Database benchmark content '.$i;
            $page->setTags([0 === $i % 2 ? 'bench-hot' : 'bench-cold', 'bucket-'.($i % 10)]);
            $page->setCustomProperty('rank', $i);
            $this->em->persist($page);

            if (0 === ($i + 1) % 250) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @param callable(): int $operation
     *
     * @return array{float, int}
     */
    private function median(callable $operation, int $repetitions = 5): array
    {
        $durations = [];
        $result = 0;
        for ($i = 0; $i < $repetitions; ++$i) {
            $start = hrtime(true);
            $result = $operation();
            $durations[] = $this->elapsedMs($start);
        }

        sort($durations);

        return [$durations[intdiv($repetitions, 2)], $result];
    }

    private function elapsedMs(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
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
