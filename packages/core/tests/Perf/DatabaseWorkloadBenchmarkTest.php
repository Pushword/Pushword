<?php

namespace Pushword\Core\Tests\Perf;

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
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Synthetic application workloads: a multisite editorial corpus and a
 * filterable catalogue. The deterministic fixtures contain no production data.
 */
#[Group('benchmark')]
final class DatabaseWorkloadBenchmarkTest extends KernelTestCase
{
    private const int HOST_COUNT = 6;

    private const int EDITORIAL_REQUESTS = 12;

    private const int LISTS_PER_REQUEST = 4;

    private const int CATALOG_SEARCHES = 24;

    private const int FACET_QUERIES = 12;

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
    public function testSyntheticApplicationWorkloads(int $volume): void
    {
        $writeStart = hrtime(true);
        $corpus = self::getContainer()->get(PageCacheSuppressor::class)->suppress(
            fn (): array => $this->seedCorpus($volume),
        );
        $writeMs = $this->elapsedMs($writeStart);
        $this->em->clear();

        [$editorialMs, $editorialRows] = $this->median(
            fn (): int => $this->runEditorialRequests($corpus['editorialTargets'], $corpus['slugsByHost']),
        );
        [$catalogMs, $catalogRows] = $this->median(fn (): int => $this->runCatalogSearches($corpus['hosts']));
        [$facetMs, $facetRows] = $this->median($this->runFacetQueries(...));
        [$exportMs, $exportRows] = $this->median(fn (): int => $this->runStaticExport($corpus['hosts']));

        $catalogCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Page::class, 'p')
            ->where('JSON_TEXT(p.tags) LIKE :catalog')
            ->setParameter('catalog', '%"content:catalog"%')
            ->getQuery()
            ->getSingleScalarResult();

        self::assertSame($volume, array_sum(array_map(count(...), $corpus['slugsByHost'])));
        self::assertSame($corpus['catalogCount'], $catalogCount);
        self::assertGreaterThan(0, $editorialRows);
        self::assertGreaterThanOrEqual(0, $catalogRows);
        self::assertGreaterThanOrEqual(0, $facetRows);
        self::assertGreaterThan(0, $exportRows);

        $result = [
            'benchmark' => 'database-workload',
            'platform' => $this->platformName(),
            'volume' => $volume,
            'write_ms' => round($writeMs, 3),
            'editorial_ms' => round($editorialMs, 3),
            'editorial_queries' => self::EDITORIAL_REQUESTS * (self::LISTS_PER_REQUEST + 2),
            'catalog_ms' => round($catalogMs, 3),
            'catalog_queries' => self::CATALOG_SEARCHES,
            'facets_ms' => round($facetMs, 3),
            'facet_queries' => self::FACET_QUERIES,
            'export_ms' => round($exportMs, 3),
        ];

        fwrite(\STDERR, '[DATABASE_WORKLOAD_BENCHMARK] '.json_encode($result, \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * @return array{
     *     hosts: list<string>,
     *     editorialTargets: list<array{host: string, locale: string, slug: string}>,
     *     slugsByHost: array<string, list<string>>,
     *     catalogCount: int
     * }
     */
    private function seedCorpus(int $volume): array
    {
        $hostCount = min(self::HOST_COUNT, $volume);
        $hosts = [];
        $rootIds = [];
        $slugsByHost = [];
        $editorialTargets = [];
        $catalogCount = 0;

        for ($i = 0; $i < $hostCount; ++$i) {
            $host = 'synthetic-site-'.($i + 1).'.test';
            $hosts[] = $host;
            $root = $this->newPage($host, 'synthetic-root', 'en', false, $i);
            $root->h1 = 'Synthetic site root';
            $this->em->persist($root);
            $slugsByHost[$host] = [$root->slug];
        }

        $this->em->flush();

        foreach ($hosts as $index => $host) {
            $root = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => 'synthetic-root']);
            self::assertNotNull($root);
            $rootIds[$index] = $root->id;
        }

        $this->em->clear();

        $index = $hostCount;
        while ($index < $volume) {
            $hostIndex = ($index * 17 + intdiv($index, 7)) % $hostCount;
            $host = $hosts[$hostIndex];
            $catalogue = 0 === $index % 3;
            $locale = ['en', 'fr', 'de'][($index + intdiv($index, 5)) % 3];
            $page = $this->newPage($host, 'synthetic-page-'.$index, $locale, $catalogue, $index);
            $page->parentPage = $this->em->getReference(Page::class, $rootIds[$hostIndex]);
            $this->em->persist($page);
            $slugsByHost[$host][] = $page->slug;
            $catalogCount += (int) $catalogue;
            if (! $catalogue) {
                $editorialTargets[] = ['host' => $host, 'locale' => $locale, 'slug' => $page->slug];
            }

            // A bounded translation graph exercises the join table without
            // making fixture construction grow quadratically.
            if (0 === $index % 5 && $index + 1 < $volume) {
                ++$index;
                $translationLocale = 'fr' === $locale ? 'en' : 'fr';
                $translation = $this->newPage($host, 'synthetic-page-'.$index, $translationLocale, $catalogue, $index);
                $translation->parentPage = $this->em->getReference(Page::class, $rootIds[$hostIndex]);
                $page->addTranslation($translation);
                $this->em->persist($translation);
                $slugsByHost[$host][] = $translation->slug;
                $catalogCount += (int) $catalogue;
                if (! $catalogue) {
                    $editorialTargets[] = ['host' => $host, 'locale' => $translationLocale, 'slug' => $translation->slug];
                }
            }

            ++$index;
            if (0 === $index % 250) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $this->em->clear();

        if ([] === $editorialTargets) {
            $editorialTargets[] = ['host' => $hosts[0], 'locale' => 'en', 'slug' => 'synthetic-root'];
        }

        return [
            'hosts' => $hosts,
            'editorialTargets' => $editorialTargets,
            'slugsByHost' => $slugsByHost,
            'catalogCount' => $catalogCount,
        ];
    }

    private function newPage(string $host, string $slug, string $locale, bool $catalogue, int $index): Page
    {
        $page = new Page();
        $page->host = $host;
        $page->locale = $locale;
        $page->slug = $slug;
        $page->h1 = 'Synthetic page '.$index;
        $page->name = 'Synthetic page '.$index;
        $page->title = 'Synthetic benchmark page '.$index;
        $page->publishedAt = 0 === $index % 10 ? null : new DateTime('-'.($index % 730).' days');

        if ($catalogue) {
            $page->mainContent = str_repeat('Synthetic catalogue description. ', 90);
            $page->setTags([
                'content:catalog',
                'category-'.($index % 8),
                'activity-'.($index % 5),
                'season-'.($index % 4),
            ]);
            $page->setCustomProperty('price', 100 + ($index * 37) % 4900);
            $page->setCustomProperty('days', 2 + $index % 20);
            $page->setCustomProperty('difficulty', 1 + $index % 5);
            $page->setCustomProperty('rating', 30 + $index % 21);
        } else {
            $page->mainContent = str_repeat('Synthetic editorial paragraph with internal context. ', 180);
            $page->setTags(['content:article', 'topic-'.($index % 12), 'audience-'.($index % 4)]);
            $page->setCustomProperty('readingTime', 2 + $index % 18);
        }

        return $page;
    }

    /**
     * @param list<array{host: string, locale: string, slug: string}> $targets
     * @param array<string, list<string>>                             $slugsByHost
     */
    private function runEditorialRequests(array $targets, array $slugsByHost): int
    {
        $rows = 0;
        for ($request = 0; $request < self::EDITORIAL_REQUESTS; ++$request) {
            $this->em->clear();
            $target = $targets[$request % \count($targets)];

            $page = $this->em->createQueryBuilder()
                ->select('p', 'parent', 'translations')
                ->from(Page::class, 'p')
                ->leftJoin('p.parentPage', 'parent')
                ->leftJoin('p.translations', 'translations')
                ->where('p.host = :host')
                ->andWhere('p.slug = :slug')
                ->setParameter('host', $target['host'])
                ->setParameter('slug', $target['slug'])
                ->getQuery()
                ->getOneOrNullResult();
            $rows += null === $page ? 0 : 1;

            for ($list = 0; $list < self::LISTS_PER_REQUEST; ++$list) {
                $rows += \count($this->em->createQueryBuilder()
                    ->select('listed')
                    ->from(Page::class, 'listed')
                    ->where('listed.host = :host')
                    ->andWhere('listed.locale = :locale')
                    ->andWhere('listed.publishedAt IS NOT NULL')
                    ->andWhere('JSON_TEXT(listed.tags) LIKE :contentType')
                    ->andWhere('JSON_TEXT(listed.tags) LIKE :topic')
                    ->orderBy('listed.publishedAt', 'DESC')
                    ->setMaxResults(12)
                    ->setParameter('host', $target['host'])
                    ->setParameter('locale', $target['locale'])
                    ->setParameter('contentType', '%"content:article"%')
                    ->setParameter('topic', '%"topic-'.(($request + $list) % 12).'"%')
                    ->getQuery()
                    ->getResult());
            }

            $rows += \count($this->em->createQueryBuilder()
                ->select('linked.id', 'linked.slug')
                ->from(Page::class, 'linked')
                ->where('linked.host = :host')
                ->andWhere('linked.slug IN (:slugs)')
                ->setParameter('host', $target['host'])
                ->setParameter('slugs', array_slice($slugsByHost[$target['host']], 0, 12))
                ->getQuery()
                ->getScalarResult());
        }

        return $rows;
    }

    /** @param list<string> $hosts */
    private function runCatalogSearches(array $hosts): int
    {
        $rows = 0;
        for ($search = 0; $search < self::CATALOG_SEARCHES; ++$search) {
            $rows += \count($this->em->createQueryBuilder()
                ->select('p.id', 'p.slug')
                ->addSelect("JSON_NUMBER(p.customProperties, '$.price') AS HIDDEN priceSort")
                ->from(Page::class, 'p')
                ->where('p.host = :host')
                ->andWhere('p.publishedAt IS NOT NULL')
                ->andWhere('JSON_TEXT(p.tags) LIKE :catalog')
                ->andWhere('JSON_TEXT(p.tags) LIKE :activity')
                ->andWhere("JSON_NUMBER(p.customProperties, '$.difficulty') <= :difficulty")
                ->andWhere("JSON_NUMBER(p.customProperties, '$.days') BETWEEN :minimumDays AND :maximumDays")
                ->andWhere("JSON_NUMBER(p.customProperties, '$.price') <= :maximumPrice")
                ->orderBy('priceSort', 0 === $search % 2 ? 'ASC' : 'DESC')
                ->setMaxResults(24)
                ->setParameter('host', $hosts[$search % \count($hosts)])
                ->setParameter('catalog', '%"content:catalog"%')
                ->setParameter('activity', '%"activity-'.($search % 5).'"%')
                ->setParameter('difficulty', 3 + $search % 3)
                ->setParameter('minimumDays', 2 + $search % 4)
                ->setParameter('maximumDays', 12 + $search % 10)
                ->setParameter('maximumPrice', 1500 + ($search % 4) * 1000)
                ->getQuery()
                ->getScalarResult());
        }

        return $rows;
    }

    private function runFacetQueries(): int
    {
        $rows = 0;
        for ($facet = 0; $facet < self::FACET_QUERIES; ++$facet) {
            $rows += (int) $this->em->createQueryBuilder()
                ->select('COUNT(p.id)')
                ->from(Page::class, 'p')
                ->where('JSON_TEXT(p.tags) LIKE :catalog')
                ->andWhere('JSON_TEXT(p.tags) LIKE :facet')
                ->setParameter('catalog', '%"content:catalog"%')
                ->setParameter('facet', '%"category-'.($facet % 8).'"%')
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $rows;
    }

    /** @param list<string> $hosts */
    private function runStaticExport(array $hosts): int
    {
        $rows = 0;
        foreach ($hosts as $host) {
            $rows += \count($this->em->createQueryBuilder()
                ->select('p.slug', 'p.locale', 'p.mainContent', 'p.tags', 'p.customProperties', 'p.updatedAt')
                ->from(Page::class, 'p')
                ->where('p.host = :host')
                ->setParameter('host', $host)
                ->getQuery()
                ->getArrayResult());
        }

        return $rows;
    }

    /**
     * @param callable(): int $operation
     *
     * @return array{float, int}
     */
    private function median(callable $operation, int $repetitions = 3): array
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
