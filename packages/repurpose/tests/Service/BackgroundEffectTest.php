<?php

namespace Pushword\Repurpose\Tests\Service;

use DOMDocument;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\SlideRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The deck-level background effect and its per-slide override (a slide with no
 * effect inherits the deck's; an explicit one — including 'none' — replaces it),
 * plus the studio's self-contained effect-thumbnail previews.
 */
#[Group('integration')]
final class BackgroundEffectTest extends KernelTestCase
{
    private function renderer(): SlideRenderer
    {
        self::bootKernel();

        return self::getContainer()->get(SlideRenderer::class);
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function render(array $spec): string
    {
        return $this->renderer()->renderSlide(new CarouselFactory()->fromArray($spec), 0);
    }

    public function testFactoryReadsDeckEffectAndLeavesAnAbsentSlideOverrideNull(): void
    {
        $carousel = new CarouselFactory()->fromArray([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'background' => 'poly-grid',
            'slides' => [['title' => 'Inherits'], ['title' => 'Overrides', 'background' => 'blobs']],
        ]);

        self::assertSame('poly-grid', $carousel->background);
        self::assertNull($carousel->slides[0]->background, 'an absent slide effect inherits the deck (null)');
        self::assertSame('blobs', $carousel->slides[1]->background);
    }

    public function testDeckEffectPaintsOnASlideWithoutItsOwn(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'background' => 'poly-grid',
            'slides' => [['title' => 'Inherits the deck effect']],
        ]);

        self::assertStringContainsString('rp-grid-0', $svg, 'the deck effect paints on a slide with no override');
    }

    public function testSlideEffectReplacesTheDeckEffect(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'background' => 'poly-grid',
            'slides' => [['title' => 'Overrides', 'background' => 'blobs']],
        ]);

        self::assertStringContainsString('rp-blob-0', $svg);
        self::assertStringNotContainsString('rp-grid-0', $svg, 'the slide override replaces the deck effect');
    }

    public function testExplicitNoneSuppressesTheInheritedDeckEffect(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'background' => 'blobs',
            'slides' => [['title' => 'Bare', 'background' => 'none']],
        ]);

        self::assertStringNotContainsString('rp-blob-', $svg, 'an explicit none overrides the deck effect to nothing');
    }

    public function testEffectPreviewIsSelfContainedSvgPaintingTheChosenEffect(): void
    {
        $svg = $this->renderer()->effectPreview('blobs');

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($svg), 'the preview is well-formed SVG');
        self::assertStringContainsString('viewBox="0 0 200 250"', $svg);
        self::assertStringContainsString('rp-blob-', $svg, 'the chosen effect is painted');
        self::assertStringContainsString('rp-thumb', $svg, 'over the slate thumbnail background, never a violet demo fill');
    }

    public function testEffectPreviewForNonePaintsOnlyTheBackground(): void
    {
        $svg = $this->renderer()->effectPreview('none');

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($svg));
        self::assertStringNotContainsString('rp-blob-', $svg);
        self::assertStringNotContainsString('<circle', $svg);
    }
}
