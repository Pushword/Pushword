<?php

namespace Pushword\Conversation\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Pushword\Conversation\Entity\Message;
use Pushword\Core\Entity\Media;

/**
 * Detaches a deleted Media from every Message referencing it through mediaList.
 *
 * mediaList is a unidirectional ManyToMany owned by Message, so — unlike Page#mainImage,
 * kept in sync by Media::removeMainImageFromPages() — Media has no inverse collection to clean.
 * The join rows are dropped by ON DELETE CASCADE, but a Message already loaded in the
 * UnitOfWork keeps the now-removed (and therefore "new") Media in memory, making the next
 * flush fail with "a new entity was found through the relationship Message#mediaList".
 * Removing it from the in-memory collections keeps the UnitOfWork consistent.
 */
#[AsEntityListener(event: Events::preRemove, entity: Media::class)]
final readonly class MediaListCleanupListener
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function preRemove(Media $media): void
    {
        /** @var Message[] $messages */
        $messages = $this->entityManager
            ->createQuery('SELECT m FROM '.Message::class.' m JOIN m.mediaList linkedMedia WHERE linkedMedia = :media')
            ->setParameter('media', $media)
            ->getResult();

        foreach ($messages as $message) {
            $message->removeMedia($media);
        }
    }
}
