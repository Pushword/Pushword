<?php

namespace Pushword\Repurpose\Tests\Service;

use DOMDocument;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\SlideRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class SlideRendererTest extends KernelTestCase
{
    private function renderer(): SlideRenderer
    {
        self::bootKernel();

        return self::getContainer()->get(SlideRenderer::class);
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function render(array $spec, int $index = 0): string
    {
        return $this->renderer()->renderSlide(new CarouselFactory()->fromArray($spec), $index);
    }

    public function testRendersWellFormedSvgWithBoundText(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hello world', 'paragraph' => 'A second line of copy.']],
        ]);

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($svg), 'the SVG is well-formed XML');
        self::assertStringContainsString('viewBox="0 0 1080 1350"', $svg);
        // Every text line is pinned to its measured width.
        self::assertStringContainsString('textLength=', $svg);
        self::assertStringContainsString('lengthAdjust="spacingAndGlyphs"', $svg);
        // The font is embedded, never linked.
        self::assertStringContainsString('data:font/ttf;base64,', $svg);
        self::assertStringNotContainsString('fonts.googleapis.com', $svg);
    }

    public function testRatioAgnosticAcrossFormats(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'pinterest', 'format' => 'pinterest-2-3',
            'slides' => [['title' => 'Tall']],
        ]);

        self::assertStringContainsString('viewBox="0 0 1000 1500"', $svg);
    }

    public function testBubblesEffectRendersCirclesWindowedPerSlide(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Bubbly', 'background' => 'bubbles']],
        ]);

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($svg), 'the bubbles effect yields well-formed SVG');
        self::assertStringContainsString('<circle', $svg);
        // Deck-wide layer is windowed through the slide frame.
        self::assertStringContainsString('clip-path="url(#frame-0)"', $svg);
    }

    public function testSwipeHintRendersAnArrowOnlyWhenEnabled(): void
    {
        $base = ['page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5'];

        $with = $this->render($base + ['slides' => [['title' => 'Swipe me', 'swipe' => true]]]);
        $without = $this->render($base + ['slides' => [['title' => 'Swipe me', 'swipe' => false]]]);

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($with), 'the swipe hint yields well-formed SVG');
        // The arrow disc + "→" path are drawn when the hint is on, and absent when off.
        self::assertStringContainsString('<circle', $with);
        self::assertStringNotContainsString('<circle', $without);
    }

    public function testMissingMediaDegradesToPlaceholderNotFatal(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Has image', 'image' => ['media' => 'does-not-exist.jpg']]],
        ]);

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($svg), 'a missing image still yields valid SVG');
        self::assertStringNotContainsString('does-not-exist', $svg);
    }
}
