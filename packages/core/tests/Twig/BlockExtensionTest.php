<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Twig\BlockExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class BlockExtensionTest extends KernelTestCase
{
    /** @return BlockExtension<object> */
    private function getBlockExtension(): BlockExtension
    {
        self::bootKernel();

        return self::getContainer()->get(BlockExtension::class);
    }

    public function testRenderAttachesWithAbsoluteUrl(): void
    {
        $ext = $this->getBlockExtension();
        $html = $ext->renderAttaches('My PDF', '/media/document.pdf', 79821);

        self::assertStringContainsString('My PDF', $html);
        self::assertStringContainsString('/media/document.pdf', $html);
        self::assertStringContainsString('download', $html);
    }

    public function testRenderAttachesWithStringSize(): void
    {
        $ext = $this->getBlockExtension();
        $html = $ext->renderAttaches('My PDF', '/media/document.pdf', '79821');

        self::assertStringContainsString('My PDF', $html);
    }

    public function testRenderAttachesWithRelativeUrl(): void
    {
        $ext = $this->getBlockExtension();
        $html = $ext->renderAttaches('My PDF', 'document.pdf', 1024);

        self::assertStringContainsString('/media/document.pdf', $html);
    }

    public function testRenderAttachesWithAnchorId(): void
    {
        $ext = $this->getBlockExtension();
        $html = $ext->renderAttaches('My PDF', '/media/document.pdf', 1024, 'my-anchor');

        self::assertStringContainsString('my-anchor', $html);
    }

    public function testRenderAttachesWithoutSize(): void
    {
        $ext = $this->getBlockExtension();
        $html = $ext->renderAttaches('My GPX', '/media/track.gpx');

        self::assertStringContainsString('My GPX', $html);
        self::assertStringContainsString('/media/track.gpx', $html);
        self::assertStringNotContainsString('bytes', $html);
    }
}
