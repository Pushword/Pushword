<?php

namespace Pushword\PageWorkflow\Workflow;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\User;
use Pushword\PageWorkflow\Entity\PageEditorialState;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Records who validated a page editorial state when it enters the "approved"
 * place of the page_editorial workflow, mirroring the createdBy/editedBy
 * convention on Page.
 */
final readonly class PageEditorialWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.page_editorial.entered.approved' => 'onApproved',
        ];
    }

    public function onApproved(EnteredEvent $enteredEvent): void
    {
        $state = $enteredEvent->getSubject();
        if (! $state instanceof PageEditorialState) {
            return;
        }

        $state->reviewedAt = new DateTime();

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $state->reviewedBy = $user;
        }

        $this->em->flush();
    }
}
