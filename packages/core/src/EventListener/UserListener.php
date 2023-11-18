<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Entity\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_user%', 'event' => 'preUpdate'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_user%', 'event' => 'prePersist'])]
final class UserListener
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordEncoder)
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
