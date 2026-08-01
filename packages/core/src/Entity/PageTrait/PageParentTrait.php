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
    public ?Page $parentPage = null {
        set {
            if (null !== $value && ! $this->validateParentPage($value)) {
                throw new LogicException("Current Page can't be it own parent page.");
            }

            $this->parentPage = $value;
        }
    }

    /**
     * @var Collection<int, Page>
     */
    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'parentPage', fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['publishedAt' => 'DESC', 'weight' => 'DESC'])]
    public Collection $childrenPages {
        get => $this->childrenPages ??= new ArrayCollection();
    }

    // todo, move to assert
    private function validateParentPage(Page $parentPage): bool
    {
        if ($parentPage === $this) {
            return false;
        }

        $grandParentPage = $parentPage->parentPage;

        return null !== $grandParentPage ? $this->validateParentPage($grandParentPage) : true;
    }

    public function hasChildrenPages(): bool
    {
        return ! $this->childrenPages->isEmpty();
    }
}
