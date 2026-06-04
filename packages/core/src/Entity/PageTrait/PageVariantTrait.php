<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Pushword\Core\Entity\Page;

/**
 * Variant pages: a page can declare itself a variant of a master page. Variants
 * carry full, independent content but consolidate onto the master for SEO
 * (canonical → master, internal links rewritten to the master). Orthogonal to
 * the parent/children tree of PageParentTrait.
 */
trait PageVariantTrait
{
    #[ORM\ManyToOne(targetEntity: Page::class, inversedBy: 'variants')]
    protected ?Page $variantOf = null;

    /**
     * @var Collection<int, Page>
     */
    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'variantOf', fetch: 'EXTRA_LAZY')]
    protected ?Collection $variants;  // @phpstan-ignore-line

    public function getVariantOf(): ?Page
    {
        return $this->variantOf;
    }

    public function setVariantOf(?Page $page): self
    {
        if (null !== $page && ! $this->validateVariantOf($page)) {
            throw new LogicException("A variant's master cannot itself be a variant, and a page cannot be its own master.");
        }

        $this->variantOf = $page;

        return $this;
    }

    /**
     * Flat hierarchy: the master must not itself be a variant (no variant-of-variant),
     * and a page cannot be its own master. This also makes cycles impossible.
     */
    private function validateVariantOf(Page $master): bool
    {
        return $master !== $this && ! $master->isVariant();
    }

    public function isVariant(): bool
    {
        return null !== $this->variantOf;
    }

    /**
     * @return Collection<int, Page>
     */
    public function getVariants(): Collection
    {
        return $this->variants ?? new ArrayCollection([]);
    }

    public function hasVariants(): bool
    {
        return ! $this->getVariants()->isEmpty();
    }
}
