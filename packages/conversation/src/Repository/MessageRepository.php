<?php

namespace Pushword\Conversation\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Conversation\Entity\Message;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return Message[]
     */
    public function getMessagesPublishedByReferring(string $referring, string $orderBy = 'createdAt DESC', int $limit = 0): mixed
    {
        $orderBy = explode(' ', $orderBy);

        $queryBuilder = $this->createQueryBuilder('m')
            ->andWhere('m.publishedAt is NOT NULL')
            ->andWhere('m.referring =  :referring OR m.tags LIKE :tag')
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
            $orConditions->add($expr->like('m.tags', ':tag'.$i));
            $tagEscaped = '%"'.addslashes($tag).'"%';
            $queryBuilder->setParameter('tag'.$i, $tagEscaped);
        }

        $queryBuilder->andWhere($orConditions);
    }

    /**
     * @param string[] $tags
     *
     * @return Message[]
     */
    public function getPublishedReviewsByTag(array $tags, int $limit = 0): array
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->andWhere('m.publishedAt is NOT NULL')
            // permits to filter only reviews
            ->andWhere('m.customProperties LIKE :noteFilter')
            ->setParameter('noteFilter', '%"rating":%');

        $this->addFilteringByTagsConditions($queryBuilder, $tags);
        $queryBuilder->orderBy('m.weight DESC,m.createdAt', 'DESC');

        if (0 !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return string[]
     */
    public function getAllTags(): array
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->select('m.tags')
            ->setMaxResults(30000); // some kind of arbitrary parapet

        /** @var array{tags: string[]}[] */
        $tags = $queryBuilder->getQuery()->getResult();

        $allTags = [];
        foreach ($tags as $entity) {
            $allTags = array_merge($allTags, $entity['tags']);
        }

        return array_values(array_unique($allTags));
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
