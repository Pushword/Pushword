<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Repurpose\Entity\SocialPost;

/**
 * @extends ServiceEntityRepository<SocialPost>
 */
class SocialPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialPost::class);
    }

    public function findOneByKey(string $host, string $page, string $network): ?SocialPost
    {
        return $this->findOneBy(['host' => $host, 'page' => $page, 'network' => $network]);
    }

    /**
     * @return SocialPost[]
     */
    public function findByHost(string $host): array
    {
        return $this->findBy(['host' => $host], ['updatedAt' => 'DESC']);
    }

    /** @return array<string, array<string, int>> */
    public function countBySourcePage(): array
    {
        /** @var list<array{host: string, page: string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('post')
            ->select('post.host AS host', 'post.page AS page', 'COUNT(post.id) AS total')
            ->where('post.page != :empty')
            ->andWhere('post.page NOT LIKE :standalone')
            ->setParameter('empty', '')
            ->setParameter('standalone', 'standalone/%')
            ->groupBy('post.host, post.page')
            ->getQuery()->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['host']][$row['page']] = (int) $row['total'];
        }

        return $counts;
    }
}
