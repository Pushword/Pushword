<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Entity\UserInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserListener
{
    public function __construct(private UserPasswordHasherInterface $passwordEncoder)
    {
    }

    /**
     * Set Password on database update if PlainPassword is set.
     */
    public function preUpdate(UserInterface $user): void
    {
        if (\is_string($user->getPlainPassword()) && '' !== $user->getPlainPassword()) {
            $user->setPassword($this->passwordEncoder->hashPassword($user, $user->getPlainPassword()));
            $user->eraseCredentials();
        }
    }

    public function prePersist(UserInterface $user): void
    {
        $this->preUpdate($user);
    }
}
