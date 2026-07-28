<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Audience;

/**
 * @extends ServiceEntityRepository<Audience>
 */
class AudienceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Audience::class);
    }

    public function findOneBySlug(string $slug): ?Audience
    {
        return $this->findOneBy(['slug' => trim(strtolower($slug))]);
    }

    /** @return list<Audience> */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['slug' => 'ASC']);
    }
}
