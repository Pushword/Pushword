<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Core\Entity\MediaUsage;

use function Safe\json_decode;

/**
 * @extends ServiceEntityRepository<MediaUsage>
 *
 * Reads go through DQL, writes through DBAL. A page save rewrites a handful of
 * rows and a rebuild writes hundreds of thousands: hydrating a MediaUsage entity
 * for either would cost far more than the row it stands for.
 */
final class MediaUsageRepository extends ServiceEntityRepository
{
    /** Rows per INSERT during a rebuild. Small enough to stay well under any placeholder limit. */
    private const int INSERT_CHUNK = 200;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaUsage::class);
    }

    /**
     * Rewrite one page's rows, and say whether anything actually moved — a page
     * edited without touching its images is the common case, and answering it with
     * one SELECT beats a DELETE plus an INSERT every time.
     *
     * @param list<array{mediaId: int, source: string}> $usages
     *
     * @return list<int> the media ids whose usage changed (before ∪ after), empty when nothing did
     */
    public function replaceForPage(int $pageId, array $usages): array
    {
        $before = $this->findRowsForPage($pageId);
        $after = [];
        foreach ($usages as $usage) {
            $after[$usage['mediaId'].'|'.$usage['source']] = $usage;
        }

        $beforeKeys = array_keys($before);
        $afterKeys = array_keys($after);
        sort($beforeKeys);
        sort($afterKeys);

        if ($beforeKeys === $afterKeys) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $connection->delete('media_usage', ['page_id' => $pageId]);

        $rows = [];
        foreach ($after as $usage) {
            $rows[] = ['mediaId' => $usage['mediaId'], 'pageId' => $pageId, 'source' => $usage['source']];
        }

        $this->insert($rows);

        return $this->mediaIdsOf([...array_values($before), ...array_values($after)]);
    }

    /** @return list<int> the media ids that were referenced by this page */
    public function deleteForPage(int $pageId): array
    {
        $before = $this->findRowsForPage($pageId);

        if ([] !== $before) {
            $this->getEntityManager()->getConnection()->delete('media_usage', ['page_id' => $pageId]);
        }

        return $this->mediaIdsOf(array_values($before));
    }

    /**
     * @param list<array{mediaId: int, source: string}> $usages
     *
     * @return list<int>
     */
    private function mediaIdsOf(array $usages): array
    {
        $mediaIds = [];
        foreach ($usages as $usage) {
            $mediaIds[$usage['mediaId']] = true;
        }

        return array_keys($mediaIds);
    }

    /**
     * Every edge, media ignored of why. One query for a whole-corpus consumer
     * (`pw:ai-index`) that needs the relation in both directions and already holds
     * the ids to resolve them against.
     *
     * @return list<array{pageId: int, mediaId: int}>
     */
    public function findAllEdges(): array
    {
        /** @var list<array{page_id: int|string, media_id: int|string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT page_id, media_id FROM media_usage',
        );

        return array_map(static fn (array $row): array => [
            'pageId' => (int) $row['page_id'],
            'mediaId' => (int) $row['media_id'],
        ], $rows);
    }

    /**
     * Why each page uses this media, keyed by page id — the question the `source`
     * column exists to answer, asked by the admin's "used on" panel.
     *
     * @return array<int, list<string>>
     */
    public function findSourcesByPageForMedia(int $mediaId): array
    {
        /** @var list<array{page_id: int|string, source: string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT page_id, source FROM media_usage WHERE media_id = ?',
            [$mediaId],
        );

        $sourcesByPage = [];
        foreach ($rows as $row) {
            $sourcesByPage[(int) $row['page_id']][] = $row['source'];
        }

        return $sourcesByPage;
    }

    /** @return list<int> */
    public function findMediaIdsForPage(int $pageId): array
    {
        /** @var list<int|string> $mediaIds */
        $mediaIds = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT media_id FROM media_usage WHERE page_id = ?',
            [$pageId],
        );

        return array_map(intval(...), $mediaIds);
    }

    public function deleteForMedia(int $mediaId): void
    {
        $this->getEntityManager()->getConnection()->delete('media_usage', ['media_id' => $mediaId]);
    }

    /**
     * SQLite does not enforce the `ON DELETE CASCADE` the join columns declare, so
     * the table is emptied here rather than left to the `media` truncation.
     */
    public function deleteAll(): void
    {
        $this->getEntityManager()->getConnection()->executeStatement('DELETE FROM media_usage');
    }

    public function hasAny(): bool
    {
        return (bool) $this->getEntityManager()->getConnection()
            ->fetchOne('SELECT 1 FROM media_usage LIMIT 1');
    }

    /** @param list<array{mediaId: int, pageId: int, source: string}> $rows */
    public function insert(array $rows): void
    {
        if ([] === $rows) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            $values = [];
            $parameters = [];
            foreach ($chunk as $row) {
                $values[] = '(?, ?, ?)';
                $parameters[] = $row['mediaId'];
                $parameters[] = $row['pageId'];
                $parameters[] = $row['source'];
            }

            $connection->executeStatement(
                'INSERT INTO media_usage (media_id, page_id, source) VALUES '.implode(', ', $values),
                $parameters,
            );
        }
    }

    /**
     * The union of the tags of the pages using each of these media, keyed by media
     * id. One JOIN for the whole set — the callers always have a set, never a single
     * media, and a query per media is the shape that made this feature slow before.
     *
     * @param list<int> $mediaIds
     *
     * @return array<int, list<string>>
     */
    public function findPageTagsByMedia(array $mediaIds): array
    {
        if ([] === $mediaIds) {
            return [];
        }

        /** @var list<array{media_id: int|string, tags: string|null}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT u.media_id, p.tags FROM media_usage u'
            .' INNER JOIN page p ON p.id = u.page_id'
            .' WHERE u.media_id IN (?)',
            [$mediaIds],
            [ArrayParameterType::INTEGER],
        );

        $tagsByMedia = [];
        foreach ($rows as $row) {
            $mediaId = (int) $row['media_id'];

            foreach ($this->decodeTags($row['tags']) as $tag) {
                $tagsByMedia[$mediaId][$tag] = true;
            }
        }

        $toReturn = [];
        foreach ($tagsByMedia as $mediaId => $tags) {
            $tagList = array_keys($tags);
            sort($tagList);
            $toReturn[$mediaId] = $tagList;
        }

        return $toReturn;
    }

    /** @return list<string> */
    private function decodeTags(?string $tags): array
    {
        if (null === $tags || '' === $tags) {
            return [];
        }

        $decoded = json_decode($tags, true);
        if (! \is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, \is_string(...)));
    }

    /**
     * Keyed `mediaId|source` so two sets compare by their sorted key lists.
     *
     * @return array<string, array{mediaId: int, source: string}>
     */
    private function findRowsForPage(int $pageId): array
    {
        /** @var list<array{media_id: int|string, source: string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT media_id, source FROM media_usage WHERE page_id = ?',
            [$pageId],
        );

        $toReturn = [];
        foreach ($rows as $row) {
            $toReturn[$row['media_id'].'|'.$row['source']] = [
                'mediaId' => (int) $row['media_id'],
                'source' => $row['source'],
            ];
        }

        return $toReturn;
    }
}
