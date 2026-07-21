<?php

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
}
