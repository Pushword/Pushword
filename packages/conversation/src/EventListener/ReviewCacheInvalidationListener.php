<?php

namespace Pushword\Conversation\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Pushword\Conversation\Entity\Message;
use Pushword\Core\Cache\RenderEpoch;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Published messages render into page output (`reviews()`, `showConversation()`),
 * so a publication change or an edit of a published message stales every page
 * that lists it. The epoch consumers (host sweep / cron incremental) do the
 * rest — no reverse message→pages lookup needed.
 *
 * Registered on Message: Review shares the metadata through single-table
 * inheritance, so both fire here.
 */
#[AsEntityListener(event: Events::postPersist, entity: Message::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Message::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Message::class)]
#[AsEntityListener(event: Events::postRemove, entity: Message::class)]
final class ReviewCacheInvalidationListener implements ResetInterface
{
    /** @var array<int, true> */
    private array $pendingBump = [];

    public function __construct(
        private readonly RenderEpoch $renderEpoch,
    ) {
    }

    public function postPersist(Message $message): void
    {
        // Visitor submissions await moderation (publishedAt null): no listing
        // shows them yet. Directly-published messages (import, admin) do count.
        if ($this->isPublished($message)) {
            $this->bump($message);
        }
    }

    public function preUpdate(Message $message, PreUpdateEventArgs $preUpdateEventArgs): void
    {
        // Publication transitions matter in both directions; any other change
        // only matters while the message is visible.
        $changeSet = $preUpdateEventArgs->getEntityChangeSet();
        if ($this->isPublished($message) || isset($changeSet['publishedAt']) || isset($changeSet['deletedAt'])) {
            $this->pendingBump[spl_object_id($message)] = true;
        }
    }

    public function postUpdate(Message $message): void
    {
        $objectId = spl_object_id($message);
        if (isset($this->pendingBump[$objectId])) {
            unset($this->pendingBump[$objectId]);
            $this->bump($message);
        }
    }

    public function postRemove(Message $message): void
    {
        if ($this->isPublished($message)) {
            $this->bump($message);
        }
    }

    public function reset(): void
    {
        $this->pendingBump = [];
    }

    /** Mirrors MessageRepository's published queries: publishedAt set, no tombstone. */
    private function isPublished(Message $message): bool
    {
        return null !== $message->publishedAt && null === $message->deletedAt;
    }

    private function bump(Message $message): void
    {
        $this->renderEpoch->bump('' === $message->host ? null : $message->host);
    }
}
