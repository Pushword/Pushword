<?php

declare(strict_types=1);

namespace Pushword\Flat\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Blocks admin save operations when a webhook lock is active.
 * This ensures content editors cannot overwrite changes being made
 * by power users through the flat file workflow.
 */
final readonly class FlatWebhookLockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private FlatLockManager $lockManager,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'onBeforePersist',
            BeforeEntityUpdatedEvent::class => 'onBeforeUpdate',
        ];
    }

    /**
     * @param BeforeEntityPersistedEvent<object> $event
     */
    public function onBeforePersist(BeforeEntityPersistedEvent $event): void
    {
        $this->checkWebhookLock($event->getEntityInstance());
    }

    /**
     * @param BeforeEntityUpdatedEvent<object> $event
     */
    public function onBeforeUpdate(BeforeEntityUpdatedEvent $event): void
    {
        $this->checkWebhookLock($event->getEntityInstance());
    }

    private function checkWebhookLock(object $entity): void
    {
        $host = $this->getHostFromEntity($entity);

        if (null === $host) {
            return;
        }

        if (! $this->lockManager->isWebhookLocked($host)) {
            return;
        }

        $lockInfo = $this->lockManager->getLockInfo($host);
        $lockedBy = $lockInfo['lockedByUser'] ?? 'external system';
        $reason = $lockInfo['reason'] ?? 'External workflow in progress';

        throw new AccessDeniedHttpException(\sprintf('Content is locked by %s: %s. View-only mode active until lock is released.', $lockedBy, $reason));
    }

    private function getHostFromEntity(object $entity): ?string
    {
        if ($entity instanceof Page) {
            return $entity->host;
        }

        if ($entity instanceof Media) {
            return null; // Media is not host-specific, check default
        }

        return null;
    }
}
