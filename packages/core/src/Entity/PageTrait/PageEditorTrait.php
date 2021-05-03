<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Pushword\Core\Entity\PageHasEditor;
use Pushword\Core\Entity\UserInterface;

trait PageEditorTrait
{
    /**
     * @ORM\ManyToOne(
     *     targetEntity="Pushword\Core\Entity\UserInterface",
     * )
     */
    protected $editedBy;

    /*
     * @ORM\OneToMany(
     *     targetEntity="Pushword\Core\Entity\PageHasEditor",
     *     mappedBy="page",
     *     cascade={"all"},
     *     orphanRemoval=true
     * )
     * @ORM\OrderBy({"editedAt": "DESC"})
     */
    //protected ArrayCollection $pageHasEditors;

    /**
     * @ORM\ManyToOne(
     *     targetEntity="Pushword\Core\Entity\UserInterface",
     * )
     */
    protected $createdBy;

    public function getEditedBy(): ?UserInterface
    {
        return $this->editedBy;
    }

    public function setEditedBy(?UserInterface $user): void
    {
        $this->editedBy = $user;
    }

    /**
     * Get targetEntity="Pushword\Core\Entity\UserInterface",.
     */
    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    /**
     * Set targetEntity="Pushword\Core\Entity\UserInterface",.
     */
    public function setCreatedBy(?UserInterface $user): void
    {
        $this->createdBy = $user;
    }

    /*
    public function setPageHasEditors($pageHasEditors): void
    {
        $this->pageHasEditors = new ArrayCollection();
        foreach ($pageHasEditors as $pageHasEditor) {
            $this->addPageHasEditor($pageHasEditor);
        }
    }

    public function getPageHasEditors(): ?ArrayCollection
    {
        return $this->pageHasEditors;
    }

    public function addPageHasEditor(PageHasEditor $pageHasEditor): void
    {
        $pageHasEditor->setPage($this);
        $this->pageHasEditors[] = $pageHasEditor;
    }

    public function resetPageHasEditors(): void
    {
        foreach ($this->pageHasEditors as $pageHasEditor) {
            $this->removePageHasEditor($pageHasEditor);
        }
    }

    public function removePageHasEditor(PageHasEditor $pageHasEditor): void
    {
        $this->pageHasEditors->removeElement($pageHasEditor);
    }
    */
}
