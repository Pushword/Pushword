<?php

declare(strict_types=1);

namespace Pushword\Admin\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Pushword\Admin\Service\PageEditLockManager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class PageEditLockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PageEditLockManager $lockManager,
        private Security $security,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityUpdatedEvent::class => 'onAfterEntityUpdated',
        ];
    }

    /**
     * Mark the page as saved when entity is updated.
     *
     * @param AfterEntityUpdatedEvent<object> $event
     */
    public function onAfterEntityUpdated(AfterEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (! $entity instanceof Page) {
            return;
        }

        $user = $this->security->getUser();

        if (! $user instanceof User || null === $entity->id) {
            return;
        }

        $this->lockManager->markSaved($entity->id, $user);
    }
}
