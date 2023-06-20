<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\PageInterface;

trait PageParentTrait
{
    #[ORM\ManyToOne(targetEntity: \Pushword\Core\Entity\PageInterface::class, inversedBy: 'childrenPages')]
    protected ?PageInterface $parentPage = null;

    /**
     * @var ?Collection<int, PageInterface>
     */
    #[ORM\OneToMany(targetEntity: \Pushword\Core\Entity\PageInterface::class, mappedBy: 'parentPage')]
    #[ORM\OrderBy(['publishedAt' => 'DESC', 'priority' => 'DESC'])]
    protected $childrenPages;

    public function getParentPage(): ?PageInterface
    {
        return $this->parentPage;
    }

    // todo, move to assert
    private function validateParentPage(PageInterface $parentPage): bool
    {
        if ($parentPage === $this) {
            return false;
        }

        return null !== $parentPage->getParentPage() ? $this->validateParentPage($parentPage->getParentPage()) : true;
    }

    public function setParentPage(?PageInterface $page): self
    {
        if (null !== $page && ! $this->validateParentPage($page)) {
            throw new \LogicException("Current Page can't be it own parent page.");
        }

        $this->parentPage = $page;

        return $this;
    }

    /**
     * @return Collection<int, PageInterface>
     */
    public function getChildrenPages()
    {
        return $this->childrenPages ?? new ArrayCollection([]);
    }

    public function hasChildrenPages(): bool
    {
        return null !== $this->childrenPages && false === $this->childrenPages->isEmpty();
    }
}
