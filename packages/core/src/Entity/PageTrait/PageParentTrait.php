<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Pushword\Core\Entity\Page;

trait PageParentTrait
{
    #[ORM\ManyToOne(targetEntity: Page::class, inversedBy: 'childrenPages')]
    protected ?Page $parentPage = null;  // @phpstan-ignore-line

    /**
     * @var Collection<int, Page>
     */
    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'parentPage')]
    #[ORM\OrderBy(['publishedAt' => 'DESC', 'weight' => 'DESC'])]
    protected ?Collection $childrenPages;  // @phpstan-ignore-line

    public function getParentPage(): ?Page
    {
        return $this->parentPage;
    }

    // todo, move to assert
    private function validateParentPage(Page $parentPage): bool
    {
        if ($parentPage === $this) {
            return false;
        }

        $grandParentPage = $parentPage->getParentPage();

        return null !== $grandParentPage ? $this->validateParentPage($grandParentPage) : true;
    }

    public function setParentPage(?Page $page): self
    {
        if (null !== $page && ! $this->validateParentPage($page)) {
            throw new LogicException("Current Page can't be it own parent page.");
        }

        $this->parentPage = $page;

        return $this;
    }

    /**
     * @return Collection<int, Page>
     */
    public function getChildrenPages(): Collection
    {
        return $this->childrenPages ?? new ArrayCollection([]);
    }

    public function hasChildrenPages(): bool
    {
        return null !== $this->childrenPages && false === $this->childrenPages->isEmpty();
    }
}
