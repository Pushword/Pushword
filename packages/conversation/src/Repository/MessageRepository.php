<?php

declare(strict_types=1);

namespace Pushword\Conversation\Repository;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Events;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Conversation\Entity\Message;
use Pushword\Core\Repository\TagsRepositoryTrait;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @extends ServiceEntityRepository<Message>
 */
#[AsDoctrineListener(event: Events::onClear)]
#[AsEntityListener(event: Events::postPersist, method: 'onMessageWrite', entity: Message::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'onMessageWrite', entity: Message::class)]
#[AsEntityListener(event: Events::postRemove, method: 'onMessageWrite', entity: Message::class)]
class MessageRepository extends ServiceEntityRepository implements ResetInterface
{
    use TagsRepositoryTrait;

    /** @var array<string, Message[]> */
    private array $publishedReviewResults = [];

    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Message::class);
    }

    public function reset(): void
    {
        $this->publishedReviewResults = [];
    }

    public function onClear(): void
    {
        $this->reset();
    }

    public function onMessageWrite(Message $message): void
    {
        $this->reset();
    }

    /**
     * @return Message[]
     */
    public function getMessagesPublishedByReferring(string $referring, string $orderBy = 'createdAt DESC', int $limit = 0): mixed
    {
        $orderBy = explode(' ', $orderBy);

        $queryBuilder = $this->createQueryBuilder('m')
            ->andWhere('m.publishedAt is NOT NULL')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.referring =  :referring OR JSON_TEXT(m.tags) LIKE :tag')
            ->setParameter('referring', $referring)
            ->setParameter('tag', '%"'.trim($referring).'"%')
            ->orderBy('m.'.$orderBy[0], $orderBy[1]);
        if (0 !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @param string[] $tags
     */
    private function addFilteringByTagsConditions(QueryBuilder $queryBuilder, array $tags): void
    {
        $orConditions = $queryBuilder->expr()->orX();

        $orConditions->add('m.referring IN (:referring)');

        $queryBuilder->setParameter('referring', $tags);

        foreach ($tags as $i => $tag) {
            $expr = $queryBuilder->expr();
            $orConditions->add($expr->like('JSON_TEXT(m.tags)', ':tag'.$i));
            $tagEscaped = '%"'.$this->escapeLikePattern($tag).'"%';
            $queryBuilder->setParameter('tag'.$i, $tagEscaped);
        }

        $queryBuilder->andWhere($orConditions);
    }

    /**
     * Escape special characters for LIKE pattern matching.
     * This properly escapes %, _, \, and " characters.
     */
    private function escapeLikePattern(string $value): string
    {
        return addcslashes($value, '%_\\"');
    }

    /**
     * @param string[] $tags
     *
     * @return Message[]
     */
    public function getPublishedReviewsByTag(array $tags, int $limit = 0, int $minRating = 0): array
    {
        $key = hash('xxh3', serialize([$tags, $limit, $minRating]));
        if (isset($this->publishedReviewResults[$key])) {
            return $this->publishedReviewResults[$key];
        }

        $queryBuilder = $this->createQueryBuilder('m')
            ->andWhere('m.publishedAt is NOT NULL')
            ->andWhere('m.deletedAt IS NULL')
            // permits to filter only reviews
            ->andWhere('JSON_TEXT(m.customProperties) LIKE :noteFilter')
            ->setParameter('noteFilter', '%"rating":%');

        if ($minRating > 0) {
            $queryBuilder->andWhere("JSON_NUMBER(m.customProperties, '$.rating') >= :minRating")
                ->setParameter('minRating', $minRating);
        }

        if ([] !== $tags) {
            $this->addFilteringByTagsConditions($queryBuilder, $tags);
        }

        $queryBuilder->orderBy('m.weight', 'DESC')
            ->addOrderBy("CASE WHEN m.content IS NOT NULL AND m.content != '' THEN 0 ELSE 1 END", 'ASC')
            ->addOrderBy('m.createdAt', 'DESC');

        if (0 !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $this->publishedReviewResults[$key] = $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return string[]
     */
    public function getAllTags(): array
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->select('m.tags')
            ->andWhere('m.deletedAt IS NULL')
            ->setMaxResults(30000);

        /** @var array{tags: string[]}[] */
        $tags = $queryBuilder->getQuery()->getResult();

        return $this->flattenTags($tags);
    }

    /**
     * Every message's uuid, keyed by id. Two scalars a row, where hydrating the
     * entities to read the same two would carry their whole content along —
     * the flat import reads this once instead of asking per CSV row.
     *
     * @return array<int, string|null>
     */
    public function getUuidById(): array
    {
        /** @var array{id: int, uuid: string|null}[] $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('m.id', 'm.uuid')
            ->getQuery()
            ->getResult();

        $uuidById = [];
        foreach ($rows as $row) {
            $uuidById[$row['id']] = $row['uuid'];
        }

        return $uuidById;
    }

    /**
     * @return Message[]
     */
    public function findByHost(string $host): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.host = :host')
            ->setParameter('host', $host)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
