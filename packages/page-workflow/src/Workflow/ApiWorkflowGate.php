<?php

namespace Pushword\PageWorkflow\Workflow;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Api\Workflow\WorkflowGateInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\PageWorkflow\Pending\PendingModification;
use Pushword\PageWorkflow\Pending\PendingModificationStorageInterface;
use Pushword\PageWorkflow\Pending\PendingPayload;
use Pushword\PageWorkflow\Repository\PageEditorialStateRepository;

/**
 * Routes an API edit to a PendingModification when the target Page is already
 * published. Mirrors `PendingModificationController`'s decision logic so the
 * API and admin behave the same.
 */
final readonly class ApiWorkflowGate implements WorkflowGateInterface
{
    public function __construct(
        private PendingModificationStorageInterface $storage,
        private PageEditorialStateRepository $editorialStateRepo,
        private EntityManagerInterface $em,
    ) {
    }

    public function intercept(Page $page, array $frontmatter, ?string $body, User $editor): array
    {
        if (! $this->isPublished($page)) {
            return ['routed' => false];
        }

        $payload = $this->extractPayload($frontmatter, $body);
        if ([] === $payload) {
            return ['routed' => false];
        }

        $pageId = $page->id;
        if (null === $pageId) {
            return ['routed' => false];
        }

        $modification = $this->storage->read($page) ?? new PendingModification($pageId, $payload);
        $modification->payload = array_merge($modification->payload, $payload);
        $modification->editedBy = $editor->getId();
        $modification->editMessage = '';

        $this->storage->write($page, $modification);
        $this->em->flush();

        return [
            'routed' => true,
            'pendingId' => $modification->pageId,
            'state' => $modification->workflowState,
        ];
    }

    public function describeEditorial(Page $page): ?array
    {
        $state = $this->editorialStateRepo->findFor($page);
        if (null === $state) {
            return null;
        }

        return [
            'state' => $state->getWorkflowState(),
        ];
    }

    /**
     * @param array<string, mixed> $frontmatter
     *
     * @return array<string, mixed>
     */
    private function extractPayload(array $frontmatter, ?string $body): array
    {
        $payload = [];
        foreach (PendingPayload::FIELDS as $field) {
            if ('mainContent' === $field) {
                if (null !== $body) {
                    $payload[$field] = $body;
                }

                continue;
            }

            if (\array_key_exists($field, $frontmatter) && \is_string($frontmatter[$field])) {
                $payload[$field] = $frontmatter[$field];
            }
        }

        return $payload;
    }

    private function isPublished(Page $page): bool
    {
        $publishedAt = $page->getPublishedAt();

        return null !== $publishedAt && $publishedAt <= new DateTimeImmutable();
    }
}
