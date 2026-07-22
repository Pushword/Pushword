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
