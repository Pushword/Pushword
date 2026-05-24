<?php

namespace Pushword\Core\EventListener;

use DateTime;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Records who validated a page when it enters the "approved" place of the
 * page_editorial workflow, mirroring the createdBy/editedBy convention.
 */
final readonly class PageWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
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
        $page = $enteredEvent->getSubject();
        if (! $page instanceof Page) {
            return;
        }

        $page->reviewedAt = new DateTime();

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $page->reviewedBy = $user;
        }
    }
}
