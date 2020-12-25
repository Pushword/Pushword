<?php

namespace Pushword\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Admin\AdminInterface as AdminAdminInterface;

/**
 * @template T of object
 *
 * @extends AdminAdminInterface<T>
 */
interface AdminInterface extends AdminAdminInterface
{
    public function getEntityManager(): EntityManagerInterface;
}
