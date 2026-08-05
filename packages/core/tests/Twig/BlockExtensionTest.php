<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\BlockExtension;

use function Safe\preg_match;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class BlockExtensionTest extends KernelTestCase
{
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

    /**
     * Gallery ids must come from the current page's counter, not `random()`:
     * re-rendering an unchanged page has to produce identical bytes (static
     * builds skip rewrites on that, content-hash caches key on it).
     */
    public function testRenderGalleryUsesDeterministicPageScopedIds(): void
    {
        $ext = $this->getBlockExtension();
        $apps = self::getContainer()->get(SiteRegistry::class);

        $apps->setCurrentPage(new Page());

        $first = $ext->renderGallery(['2.jpg' => 'A valid photo']);
        $second = $ext->renderGallery(['2.jpg' => 'A valid photo']);

        // Ids come from the page counter (Twig's `??` may consume more than one
        // increment per gallery — harmless, as long as they stay unique)…
        self::assertMatchesRegularExpression('/data-gallery="\d+"/', $first);
        self::assertNotSame($this->galleryId($first), $this->galleryId($second), 'two galleries on one page must not share an id');

        // …and the same page freshly loaded (= next build) restarts the sequence.
        $apps->setCurrentPage(new Page());
        self::assertSame($first, $ext->renderGallery(['2.jpg' => 'A valid photo']));
    }

    private function galleryId(string $html): string
    {
        preg_match('/data-gallery="(\d+)"/', $html, $matches);

        return $matches[1] ?? '';
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

    /**
     * A gallery item is a cloaked <span>, so the lightbox has no href to sniff a
     * type from: without data-type the slide opens blank.
     */
    public function testRenderGalleryDeclaresTheLightboxType(): void
    {
        $html = $this->getBlockExtension()->renderGallery(['2.jpg' => 'A valid photo']);

        self::assertStringContainsString('data-type="image"', $html);
        self::assertStringContainsString('data-dwl="/media/default/2.webp"', $html);
    }

    /** A few images sit in one centered row at native ratios, not in a cropped grid. */
    public function testRenderGalleryLaysOutFewImagesAsACenteredRow(): void
    {
        $html = $this->getBlockExtension()->renderGallery(['1.jpg' => 'A', '2.jpg' => 'B']);

        self::assertStringContainsString('flex flex-wrap justify-center', $html);
        self::assertStringNotContainsString('columns-2', $html);
        self::assertStringNotContainsString('aspect-square', $html);
    }

    /** More than four images flow into masonry columns. */
    public function testRenderGalleryLaysOutManyImagesAsColumns(): void
    {
        $html = $this->getBlockExtension()->renderGallery([
            '1.jpg' => 'A',
            '2.jpg' => 'B',
            '3.jpg' => 'C',
            'piedweb-logo.png' => 'D',
            'logo.svg' => 'E',
        ]);

        self::assertStringContainsString('columns-2 lg:columns-3', $html);
        self::assertStringContainsString('break-inside-avoid', $html);
    }

    /** An explicit gridCols keeps the historic grid layout, squares included. */
    public function testRenderGalleryExplicitGridColsKeepsTheGrid(): void
    {
        $html = $this->getBlockExtension()->renderGallery(['1.jpg' => 'A', '2.jpg' => 'B'], '3');

        self::assertStringContainsString('grid sm:grid-cols-3', $html);
        self::assertStringContainsString('aspect-square object-cover', $html);
    }

    /** Components take an optional anchor; an absent one must add no attribute at all. */
    public function testBlockWrapperId(): void
    {
        $ext = $this->getBlockExtension();

        self::assertSame('', $ext->blockWrapperId());
        self::assertSame(' id="a&quot;b"', $ext->blockWrapperId('a"b'));
    }
}
