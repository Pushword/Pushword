<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\PageHasEditor;
use Pushword\Core\Entity\UserInterface;

trait PageEditorTrait
{
    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    protected ?UserInterface $editedBy = null;

    /*
     * @ORM\OneToMany(
     *     targetEntity="Pushword\Core\Entity\PageHasEditor",
     *     mappedBy="page",
     *     cascade={"all"},
     *     orphanRemoval=true
     * )
     * @ORM\OrderBy({"editedAt": "DESC"})
     *
    protected ?ArrayCollection $pageHasEditors;
    /**/

    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    protected ?UserInterface $createdBy = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, options: ['default' => ''])]
    protected string $editMessage = '';

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

    /**
     * public function setPageHasEditors($pageHasEditors): void
     * {
     * $this->pageHasEditors = new ArrayCollection();
     * foreach ($pageHasEditors as $pageHasEditor) {
     * $this->addPageHasEditor($pageHasEditor);
     * }
     * }.
     *
     * public function getPageHasEditors(): ArrayCollection
     * {
     * return $this->pageHasEditors ?? new ArrayCollection();
     * }
     *
     * public function addPageHasEditor(PageHasEditor $pageHasEditor): void
     * {
     * $this->getPageHasEditors();
     * $pageHasEditor->setPage($this);
     * $this->pageHasEditors[] = $pageHasEditor;
     * }
     *
     * public function resetPageHasEditors(): void
     * {
     * foreach ($this->getPageHasEditors() as $pageHasEditor) {
     * $this->removePageHasEditor($pageHasEditor);
     * }
     * }
     *
     * public function removePageHasEditor(PageHasEditor $pageHasEditor): void
     * {
     * $this->getPageHasEditors()->removeElement($pageHasEditor);
     * }
     * /**/

    /**
     * Get the value of editMessage.
     */
    public function getEditMessage(): string
    {
        return $this->editMessage;
    }

    /**
     * Set the value of editMessage.
     */
    public function setEditMessage(?string $editMessage): self
    {
        $this->editMessage = (string) $editMessage;

        return $this;
    }
}
