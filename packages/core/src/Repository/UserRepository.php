<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
use Pushword\Core\Entity\UserInterface;

/**
 * @extends ServiceEntityRepository<UserInterface>
 * @implements ObjectRepository<UserInterface>
 * @implements Selectable<int, UserInterface>
 */
class UserRepository extends ServiceEntityRepository implements ObjectRepository, Selectable
{
}
