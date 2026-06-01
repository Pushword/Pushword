<?php

namespace Pushword\PageWorkflow\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Pushword\Core\Entity\Page;
use Pushword\PageWorkflow\Repository\PageEditorialStateRepository;

#[AsEntityListener(event: Events::prePersist, entity: Page::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Page::class)]
final readonly class PublishGateListener
{
    /** Workflow place that allows a page to be published. */
    private const string PUBLISHABLE_STATE = 'approved';

    public function __construct(
        private PageEditorialStateRepository $editorialStateRepo,
        private bool $requireApprovalBeforePublish = false,
    ) {
    }

    public function prePersist(Page $page): void
    {
        $this->gate($page);
    }

    public function preUpdate(Page $page, PreUpdateEventArgs $event): void
    {
        $this->gate($page, $event);
    }

    private function gate(Page $page, ?PreUpdateEventArgs $event = null): void
    {
        if (! $this->requireApprovalBeforePublish) {
            return;
        }

        if (null === $page->publishedAt) {
            return;
        }

        $state = null === $page->id ? null : $this->editorialStateRepo->findFor($page);
        if (null !== $state && self::PUBLISHABLE_STATE === $state->workflowState) {
            return;
        }

        $page->publishedAt = null;

        if (null !== $event && $event->hasChangedField('publishedAt')) {
            $event->setNewValue('publishedAt', null);
        }
    }
}
