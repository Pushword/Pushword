<?php

namespace Pushword\Core\Tests\Entity;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\VariantManager;

final class PageVariantTest extends TestCase
{
    public function testIsVariant(): void
    {
        $master = new Page();
        $variant = new Page();

        self::assertFalse($master->isVariant());
        self::assertFalse($variant->isVariant());

        $variant->variantOf = $master;

        self::assertTrue($variant->isVariant());
        self::assertSame($master, $variant->variantOf);
        self::assertFalse($master->isVariant());
    }

    public function testPageCannotBeItsOwnMaster(): void
    {
        $page = new Page();

        $this->expectException(LogicException::class);
        $page->variantOf = $page;
    }

    public function testRejectsVariantOfVariant(): void
    {
        $master = new Page();
        $variant = new Page();
        $variant->variantOf = $master;

        $third = new Page();

        // The master must not itself be a variant (flat hierarchy).
        $this->expectException(LogicException::class);
        $third->variantOf = $variant;
    }

    public function testClearingVariantOf(): void
    {
        new Page();
        $variant = new Page();

        $variant->variantOf = null;

        self::assertFalse($variant->isVariant());
        self::assertNull($variant->variantOf);
    }

    public function testPromoteThrowsOnNonVariant(): void
    {
        $manager = new VariantManager(self::createStub(EntityManagerInterface::class));

        $this->expectException(LogicException::class);
        $manager->promote(new Page());
    }
}
