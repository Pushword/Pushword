<?php

namespace Pushword\PageWorkflow\Pending;

use DateTime;
use DateTimeInterface;

/**
 * Pending modification of a published Page: snapshot of the editorial fields
 * that an editor proposes to apply, awaiting validation through the
 * page_pending_modification Symfony workflow.
 *
 * Not a Doctrine entity — persisted as JSON via a PendingModificationStorageInterface
 * implementation. Carries a workflowState field used by the workflow marking_store.
 */
final class PendingModification
{
    public string $workflowState = 'draft';

    public ?int $editedBy = null;

    public DateTimeInterface $editedAt;

    public string $editMessage = '';

    /**
     * @param int                  $pageId  the Page id this modification targets
     * @param array<string, mixed> $payload editorial fields snapshot keyed by property name
     */
    public function __construct(
        public readonly int $pageId,
        public array $payload = [],
    ) {
        $this->editedAt = new DateTime();
    }

    public function getWorkflowState(): string
    {
        return $this->workflowState;
    }

    public function setWorkflowState(?string $workflowState): self
    {
        $this->workflowState = $workflowState ?? 'draft';

        return $this;
    }
}
