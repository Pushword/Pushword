<?php

namespace Pushword\Repurpose\Tests\Service;

use DOMDocument;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\ContactSheet;

#[Group('integration')]
final class ContactSheetTest extends TestCase
{
    private function slideSvg(int $index): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1350" width="1080" height="1350" font-family="rp-body">'
            .'<rect width="1080" height="1350" fill="#0b1120"/>'
            .'<text x="10" y="10">slide '.$index.'</text></svg>';
    }

    public function testComposesNumberedScaledCells(): void
    {
        $sheet = new ContactSheet()->build([$this->slideSvg(1), $this->slideSvg(2), $this->slideSvg(3)], 1080, 1350);

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($sheet['svg']), 'the contact sheet is well-formed XML');

        // Three nested slides, scaled to the cell width through their viewBox.
        self::assertSame(3, substr_count($sheet['svg'], 'viewBox="0 0 1080 1350"'));
        self::assertSame(3, substr_count($sheet['svg'], 'width="540"'));
        // Numbered labels so an agent can reference "slide 2".
        self::assertStringContainsString('>1</text>', $sheet['svg']);
        self::assertStringContainsString('>3</text>', $sheet['svg']);
        // The full-size inner rect keeps its own dimensions (only the root was rewritten).
        self::assertStringContainsString('<rect width="1080" height="1350"', $sheet['svg']);

        self::assertGreaterThan(3 * 540, $sheet['width'], 'three columns plus gaps');
        self::assertGreaterThan(0, $sheet['height']);
    }

    public function testCalibratedCellWidthAndNote(): void
    {
        $plain = new ContactSheet()->build([$this->slideSvg(1)], 1080, 1350, 390);
        self::assertStringContainsString(' width="390"', $plain['svg']);

        $noted = new ContactSheet()->build([$this->slideSvg(1)], 1080, 1350, 390, 'Slides at 390px — the typical linkedin mobile feed width');
        self::assertStringContainsString('mobile feed width', $noted['svg']);
        self::assertGreaterThan($plain['height'], $noted['height'], 'the note gets its own band');
        self::assertSame($plain['width'], $noted['width']);
    }

    public function testWrapsIntoRowsPastFourSlides(): void
    {
        $six = array_map($this->slideSvg(...), range(1, 6));
        $sheet = new ContactSheet()->build($six, 1080, 1080);

        $four = new ContactSheet()->build(\array_slice($six, 0, 4), 1080, 1080);
        self::assertSame($four['width'], $sheet['width'], 'column count caps at 4');
        self::assertGreaterThan($four['height'], $sheet['height'], 'a second row was added');
    }

    public function testEmptyDeckStillProducesAWellFormedSheet(): void
    {
        $sheet = new ContactSheet()->build([], 1080, 1350);

        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($sheet['svg']));
        self::assertGreaterThan(0, $sheet['width']);
        self::assertGreaterThan(0, $sheet['height']);
    }
}
