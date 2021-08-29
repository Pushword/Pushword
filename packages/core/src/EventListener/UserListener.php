<?php

namespace Pushword\Core\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\PreUpdate;
use Pushword\Core\Entity\UserInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserListener
{
    protected $passwordEncoder;

    public function __construct(UserPasswordHasherInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * Set Password on database update if PlainPassword is set.
     */
    public function preUpdate(UserInterface $user, PreUpdateEventArgs $event = null)
    {
        if (\strlen($user->getPlainPassword()) > 0) {
            $user->setPassword($this->passwordEncoder->hashPassword($user, $user->getPlainPassword()));
            $user->eraseCredentials();
        }
    }

    public function prePersist(UserInterface $user, LifecycleEventArgs $event)
    {
        return $this->preUpdate($user);
    }
}
