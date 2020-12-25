<?php

namespace Pushword\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;

class MediaRepository extends ServiceEntityRepository implements ObjectRepository, Selectable
{
}
