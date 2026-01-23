<?php

namespace Pushword\Core\Entity\PageTrait;

// use Pushword\Core\Entity\PageHasEditor;
use Pushword\Core\Entity\User;

interface PageEditorInterface
{
    public function getEditedBy(): ?User;

    public function setEditedBy(?User $user): void;

    /**
     * Get targetEntity="Pushword\Core\Entity\User",.
     */
    public function getCreatedBy(): ?User;

    /**
     * Set targetEntity="Pushword\Core\Entity\User",.
     */
    public function setCreatedBy(?User $user): void;

    public function getEditMessage(): string;

    public function setEditMessage(?string $editMessage): self;

    /*
    public function setPageHasEditors($pageHasEditors): void;

    public function getPageHasEditors(): ?ArrayCollection;

    public function addPageHasEditor(PageHasEditor $pageHasEditor): void;

    public function resetPageHasEditors(): void;

    public function removePageHasEditor(PageHasEditor $pageHasEditor): void;
    */
}
