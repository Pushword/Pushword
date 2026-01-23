<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Pushword\Core\Entity\User;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements ObjectRepository<User>
 * @implements Selectable<int, User>
 */
// #[AutoconfigureTag('doctrine.repository_service')]
class UserRepository extends ServiceEntityRepository implements ObjectRepository, Selectable
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, User::class);
    }
}
