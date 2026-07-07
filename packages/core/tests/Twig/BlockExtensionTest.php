<?php

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

    public function testRenderGalleryDegradesBrokenImageAndKeepsValidOnes(): void
    {
        $ext = $this->getBlockExtension();

        $html = $ext->renderGallery([
            '2.jpg' => 'A valid photo',
            'does-not-exist-broken.jpg' => 'A photo lost in migration',
        ]);

        // The valid image still renders…
        self::assertStringContainsString('<picture', $html);
        // …while the broken one degrades to an invisible, scannable marker instead of 500-ing.
        self::assertStringContainsString('pushword:broken-image', $html);
        self::assertStringContainsString('does-not-exist-broken.jpg', $html);
    }
}
