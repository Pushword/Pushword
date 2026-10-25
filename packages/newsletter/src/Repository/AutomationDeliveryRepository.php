<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\AutomationDelivery;

/**
 * @extends ServiceEntityRepository<AutomationDelivery>
 */
class AutomationDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AutomationDelivery::class);
    }
}
