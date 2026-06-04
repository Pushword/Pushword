<?php

namespace Pushword\Core\Tests\Entity;

use LogicException;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;

final class PageVariantTest extends TestCase
{
    public function testIsVariant(): void
    {
        $master = new Page();
        $variant = new Page();

        self::assertFalse($master->isVariant());
        self::assertFalse($variant->isVariant());

        $variant->setVariantOf($master);

        self::assertTrue($variant->isVariant());
        self::assertSame($master, $variant->getVariantOf());
        self::assertFalse($master->isVariant());
    }

    public function testPageCannotBeItsOwnMaster(): void
    {
        $page = new Page();

        $this->expectException(LogicException::class);
        $page->setVariantOf($page);
    }

    public function testRejectsVariantOfVariant(): void
    {
        $master = new Page();
        $variant = new Page();
        $variant->setVariantOf($master);

        $third = new Page();

        // The master must not itself be a variant (flat hierarchy).
        $this->expectException(LogicException::class);
        $third->setVariantOf($variant);
    }

    public function testClearingVariantOf(): void
    {
        $master = new Page();
        $variant = new Page();
        $variant->setVariantOf($master);

        $variant->setVariantOf(null);

        self::assertFalse($variant->isVariant());
        self::assertNull($variant->getVariantOf());
    }
}
