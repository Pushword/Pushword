<?php

namespace Pushword\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as sfUserInterface;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('email', message: 'user.email.already_used')]
class User implements UserInterface, sfUserInterface, PasswordAuthenticatedUserInterface
{
    use CustomPropertiesTrait;
    use UserTrait;

    #[ORM\Id, ORM\Column(type: Types::INTEGER), ORM\GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;
}
