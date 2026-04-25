<?php

namespace Pushword\Core\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Pushword\Core\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsDoctrineListener(event: Events::loadClassMetadata)]
final readonly class EntityMetadataOverrideListener
{
    public function __construct(
        #[Autowire('%pw.entity_user%')]
        private string $entityUserClass,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        if (User::class === $metadata->getName() && User::class !== $this->entityUserClass) {
            $metadata->isMappedSuperclass = true;
        }
    }
}
