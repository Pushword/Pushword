<?php

namespace Pushword\Repurpose\Tests\Service;

use DOMDocument;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Repurpose\Model\Creator;
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
        // Each text block is tagged with the spec field it came from (click-to-edit).
        self::assertStringContainsString('data-rp-edit="title"', $svg);
        self::assertStringContainsString('data-rp-edit="paragraph"', $svg);
    }

    public function testExplicitNewlinesSplitTextLines(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => "Two lines\nwanted"]],
        ]);

        self::assertStringContainsString('>Two lines</text>', $svg);
        self::assertStringContainsString('>wanted</text>', $svg);
    }

    public function testFreeTextBoxRendersAtItsFractionalPosition(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hi', 'texts' => [
                ['content' => 'Anywhere', 'x' => 0.1, 'y' => 0.4, 'size' => 0.05, 'color' => '#ff0000'],
            ]]],
        ]);

        // Left edge 0.1 × 1080, size 0.05 × 1080, baseline 0.4 × 1350 + 54 × 0.82.
        self::assertStringContainsString('x="108"', $svg);
        self::assertStringContainsString('y="584.28"', $svg);
        self::assertStringContainsString('font-size="54"', $svg);
        self::assertStringContainsString('fill="#ff0000"', $svg);
        self::assertStringContainsString('>Anywhere</text>', $svg);
        self::assertStringContainsString('data-rp-edit="texts.0"', $svg);
    }

    /**
     * A tiny stated size must be honoured, not silently *enlarged*: TextLayout's
     * shrink loop never runs when the start size is below its minimum, and would
     * lay the text out at the minimum instead — the renderer guards by capping
     * the minimum at the requested size.
     */
    public function testTinyFreeTextSizeIsHonoured(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['texts' => [['content' => 'small print', 'size' => 0.012]]]],
        ]);

        // 0.012 × 1080 = 12.96px — below the 0.015 × width floor.
        self::assertStringContainsString('font-size="12.96"', $svg);
    }

    /**
     * A box too close to the frame bottom for its stated size shrinks to fit —
     * free placement never escapes the no-overflow guarantee.
     */
    public function testFreeTextShrinksInsteadOfOverflowingTheFrameBottom(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['texts' => [['content' => 'Almost at the very bottom edge', 'y' => 0.97, 'size' => 0.2]]]],
        ]);

        self::assertStringContainsString('>Almost at the very bottom edge</text>', $svg);
        self::assertStringNotContainsString('font-size="216"', $svg);
    }

    public function testFreeTextAlignmentAnchorsInsideItsBox(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['texts' => [['content' => 'Right', 'x' => 0.1, 'width' => 0.5, 'align' => 'right']]]],
        ]);

        // Anchored at the box's right edge: (0.1 + 0.5) × 1080.
        self::assertStringContainsString('text-anchor="end"', $svg);
        self::assertStringContainsString('x="648"', $svg);
        // No stated colour: the slide's text colour applies.
        self::assertStringContainsString('fill="#f8fafc"', $svg);
    }

    public function testHighlightPaintsAMarkerBehindEachLine(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [[
                'title' => "One\nTwo", 'highlight' => '#fde047',
                'texts' => [['content' => 'Note', 'highlight' => '#22c55e']],
            ]],
        ]);

        // One rounded marker per title line, plus one behind the free text.
        self::assertSame(2, substr_count($svg, 'fill="#fde047"'));
        self::assertSame(1, substr_count($svg, 'fill="#22c55e"'));
        self::assertStringContainsString('rx=', $svg);
    }

    public function testRatioAgnosticAcrossFormats(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'pinterest', 'format' => 'pinterest-2-3',
            'slides' => [['title' => 'Tall']],
        ]);

        self::assertStringContainsString('viewBox="0 0 1000 1500"', $svg);
    }

    public function testPatternEffectTilesAndAlignsAcrossSlides(): void
    {
        $spec = [
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'background' => 'waves',
            'slides' => [['title' => 'One'], ['title' => 'Two']],
        ];
        $first = $this->render($spec, 0);
        $second = $this->render($spec, 1);

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($first), 'the pattern effect yields well-formed SVG');
        self::assertStringContainsString('<pattern id="rp-pat-waves-0"', $first);
        // The tile is shifted by one slide width on the next slide so the pattern
        // lines up continuously across a swipe.
        self::assertStringContainsString('patternTransform="translate(0 0)"', $first);
        self::assertStringContainsString('patternTransform="translate(-1080 0)"', $second);
    }

    public function testSplitVerticalStacksTwoImageCells(): void
    {
        // Missing media proves the cell geometry without a fixture: split-v paints
        // two full-width, half-height cells, the second offset by half the height.
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [[
                'title' => 'Split',
                'imageLayout' => 'split-v',
                'images' => [['media' => 'nope-top.jpg'], ['media' => 'nope-bottom.jpg']],
            ]],
        ]);

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($svg), 'a split slide yields well-formed SVG');
        self::assertStringContainsString('<rect x="0" y="0" width="1080" height="675" fill="'.SlideRenderer::MISSING_MEDIA_BG.'"', $svg);
        self::assertStringContainsString('<rect x="0" y="675" width="1080" height="675" fill="'.SlideRenderer::MISSING_MEDIA_BG.'"', $svg);
        self::assertStringNotContainsString('nope-top', $svg);
    }

    public function testSplitHorizontalSetsTwoImageCellsSideBySide(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [[
                'title' => 'Split',
                'imageLayout' => 'split-h',
                'images' => [['media' => 'nope-left.jpg'], ['media' => 'nope-right.jpg']],
            ]],
        ]);

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($svg), 'a split slide yields well-formed SVG');
        self::assertStringContainsString('<rect x="0" y="0" width="540" height="1350" fill="'.SlideRenderer::MISSING_MEDIA_BG.'"', $svg);
        self::assertStringContainsString('<rect x="540" y="0" width="540" height="1350" fill="'.SlideRenderer::MISSING_MEDIA_BG.'"', $svg);
    }

    public function testPaperGrainIsStrongEnoughToBeVisible(): void
    {
        $svg = $this->render([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Grainy', 'background' => 'paper']],
        ]);

        self::assertStringContainsString('feTurbulence', $svg);
        // Regression guard: 0.05 was invisible at feed size (enduser report).
        self::assertStringContainsString('opacity="0.12"', $svg);
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

    /**
     * @param array<string, mixed> $carouselExtra
     */
    private function renderWithCreator(array $carouselExtra, Creator $creator): string
    {
        $spec = $carouselExtra + [
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Bylined']],
        ];

        return $this->renderer()->renderSlide(new CarouselFactory()->fromArray($spec), 0, $creator);
    }

    public function testCreatorWithoutAvatarGetsAnInitialsDisc(): void
    {
        $svg = $this->renderWithCreator([], new Creator('Jane Doe', role: 'Guest editor'));

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($svg), 'the byline yields well-formed SVG');
        // The initials disc anchors the byline when no portrait is configured.
        self::assertStringContainsString('>JD</text>', $svg);
        self::assertStringContainsString('Jane Doe', $svg);
        self::assertStringContainsString('Guest editor', $svg);
    }

    public function testInitialsHandleSingleWordAndAccentedNames(): void
    {
        // Brand byline ("Pushword") → one letter; accents keep their case fold.
        self::assertStringContainsString('>P</text>', $this->renderWithCreator([], new Creator('Pushword')));
        self::assertStringContainsString('>ÉZ</text>', $this->renderWithCreator([], new Creator('Émile Zola')));
    }

    public function testCreatorOrientationChangesTheBylineLayout(): void
    {
        $horizontal = $this->renderWithCreator([], new Creator('Jane Doe', role: 'Guest editor'));
        $vertical = $this->renderWithCreator(['creatorOrientation' => 'vertical'], new Creator('Jane Doe', role: 'Guest editor'));

        // Regression pin: the orientation knob used to be parsed but never read.
        self::assertNotSame($horizontal, $vertical, 'vertical stacks the byline under the avatar');

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($vertical), 'the vertical byline yields well-formed SVG');
        self::assertStringContainsString('Jane Doe', $vertical);
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
