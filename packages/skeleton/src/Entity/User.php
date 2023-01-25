<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\User as BaseUser;
use Pushword\Core\Repository\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User extends BaseUser
{
}
