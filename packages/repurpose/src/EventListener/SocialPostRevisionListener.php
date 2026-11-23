<?php

declare(strict_types=1);

namespace Pushword\Repurpose\EventListener;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Pushword\Repurpose\Doctrine\CompressedJsonType;
use Pushword\Repurpose\Entity\SocialPost;

#[AsEntityListener(event: Events::preUpdate, entity: SocialPost::class)]
#[AsEntityListener(event: Events::postUpdate, entity: SocialPost::class)]
final class SocialPostRevisionListener
{
    public function preUpdate(SocialPost $post, PreUpdateEventArgs $event): void
    {
        if ($event->hasChangedField('spec')) {
            $post->updatedAt = new DateTime();
        }
    }

    public function postUpdate(SocialPost $post, PostUpdateEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $changes = $entityManager->getUnitOfWork()->getEntityChangeSet($post);
        if (! isset($changes['spec'])) {
            return;
        }

        // The optimistic write has succeeded. Archive the previous spec inside
        // the same transaction, including the first edit of pre-existing posts.
        /** @var DateTimeInterface $savedAt */
        $savedAt = $changes['updatedAt'][0] ?? $post->updatedAt;
        $entityManager->getConnection()->insert('social_post_revision', [
            'post_id' => $post->id,
            'version' => $post->version - 1,
            'saved_at' => DateTimeImmutable::createFromInterface($savedAt),
            'spec' => $changes['spec'][0],
        ], ['saved_at' => Types::DATETIME_IMMUTABLE, 'spec' => CompressedJsonType::NAME]);
    }
}
