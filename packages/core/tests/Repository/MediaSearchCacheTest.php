<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Repository;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Cache\RenderEpoch;
use Pushword\Core\Entity\Media;
use Pushword\Core\EventListener\MediaCacheInvalidationListener;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Tests\Perf\QueryCountingTrait;
use Pushword\Core\Utils\SearchNormalizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[Group('integration')]
final class MediaSearchCacheTest extends KernelTestCase
{
    use QueryCountingTrait;

    private EntityManagerInterface $em;

    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->cache = new ArrayAdapter();
        $this->startCountingQueries($this->em->getConnection());
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->clear();
        $this->stopCountingQueries();
        parent::tearDown();
    }

    /** @return iterable<string, array{string}> */
    public static function searches(): iterable
    {
        foreach (['logo', 'Demo 1', '%', '_', '', 'missing-cache-media'] as $search) {
            yield 'search: '.$search => [$search];
        }
    }

    #[DataProvider('searches')]
    public function testWarmSearchesKeepResultsAndOrderWithoutTableScans(string $search): void
    {
        $repo = $this->repository();
        $expected = array_map(static fn (Media $media): ?int => $media->id, $repo->findBySearch($search));
        $expectedFirst = $repo->findOneBySearch($search)?->id;
        $this->em->clear();
        $fresh = $this->repository();

        $queries = $this->countQueries(function () use ($fresh, $search, $expected, $expectedFirst): void {
            $results = $fresh->findBySearch($search);
            self::assertSame($expected, array_map(static fn (Media $media): ?int => $media->id, $results));
            self::assertSame($expectedFirst, $fresh->findOneBySearch($search)?->id);
            foreach ($results as $media) {
                self::assertTrue($this->em->contains($media), 'Cached entities must belong to the current EntityManager.');
            }
        });

        self::assertSame([] === $expected ? 0 : 1, $queries, 'Warm searches should only hydrate matches by primary key.');
    }

    public function testColdSearchesStillUseOneQuery(): void
    {
        $this->em->clear();
        $repo = $this->repository();

        self::assertSame(1, $this->countQueries(static fn (): array => $repo->findBySearch('1.jpg')));
        self::assertSame(1, $this->countQueries(static fn (): ?Media => $repo->findOneBySearch('1.jpg')));
    }

    public function testHistoryAndNormalizedAltKeepTheirRankingAcrossInstances(): void
    {
        $alt = $this->insertMedia('cache-alt.jpg', alt: 'Été montagne');
        $accented = $this->insertMedia('cache-accented.jpg', alt: 'Été montagne');
        $history = $this->insertMedia('cache-renamed.jpg', history: ['cache-ranking.jpg']);
        $filename = $this->insertMedia('cache-ranking.webp');
        $this->em->getConnection()->update('media', ['name' => 'cache-ranking', 'name_search' => 'cache-ranking'], ['id' => $alt]);

        foreach ([$this->repository(), $this->repository()] as $repo) {
            self::assertSame($filename, $repo->findOneBySearch('cache-ranking')?->id);
            self::assertSame($history, $repo->findOneBySearch('cache-ranking.jpg')?->id);
            self::assertSame($accented, $repo->findOneBySearch('ete montagne')?->id);
            self::assertCount(3, $repo->findBySearch('cache-ranking'));
        }
    }

    public function testMediaWritesInvalidateMatchesAndCachedMisses(): void
    {
        $repo = $this->repository();
        self::assertSame([], $repo->findBySearch('cache-written'));
        self::assertNull($repo->findOneBySearch('cache-written'));
        $id = $this->insertMedia('cache-written.jpg');
        $listener = new MediaCacheInvalidationListener($this->cache, $this->em, self::getContainer()->get(RenderEpoch::class));
        $listener->postPersist();

        $fresh = $this->repository();
        self::assertSame($id, $fresh->findOneBySearch('cache-written')?->id);
        self::assertCount(1, $fresh->findBySearch('cache-written'));

        $this->em->getConnection()->update('media', ['media' => 'cache-replaced.jpg'], ['id' => $id]);
        $this->em->clear();
        $listener->postUpdate();
        $updated = $this->repository();
        self::assertNull($updated->findOneBySearch('cache-written'));
        self::assertSame([], $updated->findBySearch('cache-written'));
        self::assertSame($id, $updated->findOneBySearch('cache-replaced')?->id);
    }

    public function testMissingCachedIdsRebuildBothSearchesAfterOutOfBandDeletion(): void
    {
        $first = $this->insertMedia('cache-deletion-first.jpg');
        $second = $this->insertMedia('cache-deletion-second.jpg');
        $repo = $this->repository();
        self::assertCount(2, $repo->findBySearch('cache-deletion'));
        $selected = $repo->findOneBySearch('cache-deletion');
        self::assertNotNull($selected);
        $deleted = $selected->id;
        $remaining = $deleted === $first ? $second : $first;
        $this->em->clear();
        $this->em->getConnection()->delete('media', ['id' => $deleted]);

        $fresh = $this->repository();
        self::assertSame($remaining, $fresh->findOneBySearch('cache-deletion')?->id);
        self::assertSame([$remaining], array_map(static fn (Media $media): ?int => $media->id, $fresh->findBySearch('cache-deletion')));
        $this->em->clear();
        $repaired = $this->repository();
        self::assertSame(1, $this->countQueries(static fn (): array => $repaired->findBySearch('cache-deletion')));
        self::assertSame(0, $this->countQueries(static fn (): ?Media => $repaired->findOneBySearch('cache-deletion')));
    }

    public function testDebugSearchesBypassThePersistentCache(): void
    {
        self::assertSame([], $this->repository()->findBySearch('cache-debug'));
        self::assertNull($this->repository()->findOneBySearch('cache-debug'));
        $id = $this->insertMedia('cache-debug.jpg');
        $repo = $this->repository(debug: true);

        self::assertSame($id, $repo->findOneBySearch('cache-debug')?->id);
        self::assertCount(1, $repo->findBySearch('cache-debug'));
    }

    public function testCachedMatchesHydrateCurrentDerivedTags(): void
    {
        $id = $this->insertMedia('cache-derived-tags.jpg');
        self::assertCount(1, $this->repository()->findBySearch('cache-derived-tags'));
        $this->em->getRepository(Media::class)->updatePageTags($id, ['updated-without-media-events']);
        $this->em->clear();

        $results = $this->repository()->findBySearch('cache-derived-tags');
        self::assertCount(1, $results);
        self::assertSame(['updated-without-media-events'], $results[0]->pageTags);
    }

    private function repository(bool $debug = false): MediaRepository
    {
        return new MediaRepository(self::getContainer()->get(ManagerRegistry::class), $this->cache, $debug);
    }

    /** @param list<string> $history */
    private function insertMedia(string $filename, array $history = [], string $alt = ''): int
    {
        $connection = $this->em->getConnection();
        $row = $connection->fetchAssociative('SELECT * FROM media WHERE media = ?', ['1.jpg']);
        self::assertIsArray($row);
        unset($row['id']);
        $row['media'] = $filename;
        $row['file_name_history'] = json_encode($history, \JSON_THROW_ON_ERROR);
        $row['name'] = $alt;
        $row['name_search'] = SearchNormalizer::normalize($alt);
        $connection->insert('media', $row, ['hidden_from_admin' => Types::BOOLEAN]);

        $id = $connection->fetchOne('SELECT id FROM media WHERE media = ?', [$filename]);
        self::assertIsNumeric($id);

        return (int) $id;
    }
}
