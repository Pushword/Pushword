<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
use Pushword\Core\Entity\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @extends ServiceEntityRepository<UserInterface>
 *
 * @implements ObjectRepository<UserInterface>
 * @implements Selectable<int, UserInterface>
 */
#[AutoconfigureTag('doctrine.repository_service')]
class UserRepository extends ServiceEntityRepository implements ObjectRepository, Selectable
{
}
