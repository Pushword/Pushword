<?php

namespace Pushword\PageWorkflow\Workflow;

use DateTime;
use Pushword\Core\Entity\User;
use Pushword\PageWorkflow\Pending\PendingModification;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

/**
 * Stamps editedBy/editedAt on every transition of the pending modification
 * workflow, mirroring the PageEditorialWorkflowSubscriber convention.
 */
final readonly class PendingModificationWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(private Security $security)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.page_pending_modification.entered' => 'onEntered',
        ];
    }

    public function onEntered(Event $event): void
    {
        $modification = $event->getSubject();
        if (! $modification instanceof PendingModification) {
            return;
        }

        $modification->editedAt = new DateTime();

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $modification->editedBy = $user->id;
        }
    }
}
