<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_user%', 'event' => 'preUpdate'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_user%', 'event' => 'prePersist'])]
final readonly class UserListener
{
    public function __construct(private UserPasswordHasherInterface $passwordEncoder)
    {
    }

    /**
     * Set Password on database update if PlainPassword is set.
     */
    public function preUpdate(User $user): void
    {
        if ('' !== $user->getPlainPassword()) {
            $user->setPassword($this->passwordEncoder->hashPassword($user, $user->getPlainPassword()));
            $user->eraseCredentials();
        }
    }

    public function prePersist(User $user): void
    {
        $this->preUpdate($user);
    }
}
