<?php

namespace Pushword\Core\Entity;

use Symfony\Component\Security\Core\User\UserInterface as BaseUserInterface;

interface UserInterface extends BaseUserInterface
{
    const ROLE_DEFAULT = 'ROLE_USER';

    const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    public function getPlainPassword();

    public function setPassword(string $password);
}
