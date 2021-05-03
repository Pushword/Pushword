<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
//use Pushword\Core\Entity\PageHasEditor;
use Pushword\Core\Entity\UserInterface;

interface PageEditorInterface
{
    public function getEditedBy(): ?UserInterface;

    public function setEditedBy(?UserInterface $user): void;

    /**
     * Get targetEntity="Pushword\Core\Entity\UserInterface",.
     */
    public function getCreatedBy(): ?UserInterface;

    /**
     * Set targetEntity="Pushword\Core\Entity\UserInterface",.
     */
    public function setCreatedBy(?UserInterface $user): void;

    /*
    public function setPageHasEditors($pageHasEditors): void;

    public function getPageHasEditors(): ?ArrayCollection;

    public function addPageHasEditor(PageHasEditor $pageHasEditor): void;

    public function resetPageHasEditors(): void;

    public function removePageHasEditor(PageHasEditor $pageHasEditor): void;
    */
}
