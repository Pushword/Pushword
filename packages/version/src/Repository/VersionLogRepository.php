<?php

namespace Pushword\Version\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Version\Entity\VersionLog;

/**
 * @extends ServiceEntityRepository<VersionLog>
 */
class VersionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VersionLog::class);
    }
}
