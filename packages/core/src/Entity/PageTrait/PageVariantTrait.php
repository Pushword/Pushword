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
    public ?Page $variantOf = null {
        set {
            if (null !== $value && ! $this->validateVariantOf($value)) {
                throw new LogicException("A variant's master cannot itself be a variant, and a page cannot be its own master.");
            }

            $this->variantOf = $value;
        }
    }

    /**
     * @var Collection<int, Page>
     */
    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'variantOf', fetch: 'EXTRA_LAZY')]
    public Collection $variants {
        get => $this->variants ??= new ArrayCollection();
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

    public function hasVariants(): bool
    {
        return ! $this->variants->isEmpty();
    }
}
