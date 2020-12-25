<?php

namespace Pushword\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin as SonataAbstractAdmin;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @template T of object
 *
 * @extends SonataAbstractAdmin<T>
 *
 * @implements AdminInterface<T>
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class AbstractAdmin extends SonataAbstractAdmin implements AdminInterface
{
    #[Required]
    public EntityManagerInterface $entityManager;

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
