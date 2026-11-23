<?php

declare(strict_types=1);

namespace Pushword\Core\Collaboration;

use Pushword\Core\Entity\Page;
use Pushword\Core\Service\RevisionCalculator;

final class LiveWrite
{
    private ?object $entity = null;

    private ?string $pageBeforeWrite = null;

    private ?string $previousEditor = null;

    private bool $checkpoint = false;

    public function __construct(private readonly RevisionCalculator $revisions)
    {
    }

    public function begin(object $entity, bool $checkpoint): void
    {
        $this->entity = $entity;
        $this->checkpoint = $checkpoint;
        if ($checkpoint && $entity instanceof Page) {
            $this->pageBeforeWrite = $this->revisions->serialize($entity);
            $this->previousEditor = $entity->editedBy?->getUserIdentifier();
        }
    }

    public function end(): void
    {
        $this->entity = null;
        $this->checkpoint = false;
        $this->pageBeforeWrite = null;
        $this->previousEditor = null;
    }

    public function isActive(object $entity): bool
    {
        return $this->entity === $entity;
    }

    public function archivesPrevious(): bool
    {
        return $this->checkpoint;
    }

    /** @return array{string, ?string}|null */
    public function previousPage(): ?array
    {
        return null === $this->pageBeforeWrite ? null : [$this->pageBeforeWrite, $this->previousEditor];
    }
}
