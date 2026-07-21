<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\TextLayout;

#[Group('integration')]
final class TextLayoutTest extends TestCase
{
    private const string FONT = __DIR__.'/../../src/Resources/font/roboto-regular.ttf';

    private TextLayout $layout;

    protected function setUp(): void
    {
        $this->layout = new TextLayout();
    }

    /**
     * The load-bearing unit fix, asserted in a FreeType-version-independent way:
     * imagettfbbox scales ~linearly with size, so measuring at cssPx (fed as
     * points) lands at 72/96 of measuring at cssPx points naïvely (±1px of integer
     * rounding). If this drifts, the validator would over-report width by ~33% and
     * shorten correct copy forever.
     */
    public function testMeasureAppliesThePointToPixelFactor(): void
    {
        $text = 'Repurpose your article';
        $naive = imagettfbbox(64, 0, self::FONT, $text);
        self::assertNotFalse($naive);
        $right = $naive[2];
        $left = $naive[0];
        self::assertIsInt($right);
        self::assertIsInt($left);
        $naiveWidth = abs($right - $left);

        $measured = $this->layout->measureWidth($text, self::FONT, 64);

        self::assertEqualsWithDelta($naiveWidth * TextLayout::PT_PER_PX, $measured, 2.0);
    }

    public function testMeasureIsLinearInSize(): void
    {
        $a = $this->layout->measureWidth('Hello world', self::FONT, 40);
        $b = $this->layout->measureWidth('Hello world', self::FONT, 80);

        self::assertEqualsWithDelta($a * 2, $b, 2.0);
    }

    public function testWrapBreaksIntoExpectedLines(): void
    {
        // A wide title in a narrow frame must wrap to several lines.
        $lines = $this->layout->wrap('Turn your article into a scroll-stopping carousel', self::FONT, 96, 900);

        self::assertGreaterThan(1, \count($lines));
        foreach ($lines as $line) {
            self::assertLessThanOrEqual(900.5, $line->width, 'no line exceeds the max width');
            self::assertGreaterThan(0, $line->width);
        }
    }

    public function testShrinkToFitReducesSizeUntilItFits(): void
    {
        $long = 'A deliberately long headline that will not fit at the starting size';
        $laid = $this->layout->layout($long, self::FONT, 600, 300, 120, 24);

        self::assertLessThan(120, $laid->fontSize, 'the size was reduced to fit');
        self::assertFalse($laid->overflow);
        self::assertLessThanOrEqual(300.5, $laid->height());
    }

    public function testOverflowFlaggedWhenItCannotFitAtMinSize(): void
    {
        // A frame too small for even the minimum size must flag overflow.
        $laid = $this->layout->layout('Way too much text to ever fit in here', self::FONT, 120, 40, 96, 40);

        self::assertTrue($laid->overflow);
    }

    public function testEmptyTextProducesNoLines(): void
    {
        $laid = $this->layout->layout('   ', self::FONT, 500, 500);

        self::assertTrue($laid->isEmpty());
        self::assertFalse($laid->overflow);
    }

    /**
     * French spacing before `? ! ; :` must never wrap the punctuation alone onto
     * its own line ("Naxos ou Paros" / "?").
     */
    public function testTerminalPunctuationNeverWrapsAlone(): void
    {
        // A width tight enough that "Paros ?" would otherwise split after "Paros".
        $width = $this->layout->measureWidth('Naxos ou Paros', self::FONT, 48) + 1;
        $lines = $this->layout->wrap('Naxos ou Paros ?', self::FONT, 48, $width);

        self::assertGreaterThan(1, \count($lines));
        foreach ($lines as $line) {
            self::assertNotSame('?', trim($line->text), 'the question mark stayed glued to its word');
        }

        $last = end($lines);
        self::assertNotFalse($last);
        self::assertStringEndsWith("\u{00A0}?", $last->text);
    }

    public function testGuillemetsStayGluedToTheirWord(): void
    {
        // Width tight enough to force a break inside the quoted phrase.
        $width = $this->layout->measureWidth('Il a dit', self::FONT, 48) + 1;
        $lines = $this->layout->wrap('Il a dit « oui » à la fin', self::FONT, 48, $width);

        self::assertGreaterThan(1, \count($lines));
        foreach ($lines as $line) {
            self::assertNotSame('«', trim($line->text));
            self::assertNotSame('»', trim($line->text));
        }
    }
}
