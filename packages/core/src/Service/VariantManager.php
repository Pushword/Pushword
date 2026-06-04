<?php

namespace Pushword\Core\Service;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Pushword\Core\Entity\Page;

/**
 * Reversibility for variant pages. Mutates the owning side (`variantOf`) only;
 * the caller is responsible for flushing.
 */
final readonly class VariantManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Promote a variant to master: its former master and all sibling variants
     * become variants of the promoted page.
     */
    public function promote(Page $variant): void
    {
        $master = $variant->getVariantOf();

        if (null === $master) {
            throw new LogicException('Only a variant can be promoted to master.');
        }

        $siblings = $this->variantsOf($master);

        // Detach first so the flat-hierarchy validation accepts the rewiring below.
        $variant->setVariantOf(null);

        foreach ($siblings as $sibling) {
            if ($sibling !== $variant) {
                $sibling->setVariantOf($variant);
            }
        }

        $master->setVariantOf($variant);
    }

    /**
     * Repoint a master's variants onto a freshly promoted one before the master
     * is removed, so no variant is orphaned and the self-FK stays satisfied.
     */
    public function promoteOnMasterRemoval(Page $master): void
    {
        $variants = $this->variantsOf($master);

        if ([] === $variants) {
            return;
        }

        $newMaster = array_shift($variants);
        $newMaster->setVariantOf(null);

        foreach ($variants as $variant) {
            $variant->setVariantOf($newMaster);
        }
    }

    /**
     * @return Page[]
     */
    private function variantsOf(Page $master): array
    {
        return $this->entityManager->getRepository(Page::class)->findBy(['variantOf' => $master]);
    }
}
