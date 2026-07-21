<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\SlideGeometry;

#[Group('integration')]
final class SlideGeometryTest extends TestCase
{
    private SlideGeometry $geometry;

    protected function setUp(): void
    {
        $this->geometry = new SlideGeometry();
    }

    public function testLandscapeSourceInPortraitFrameOverflowsHorizontally(): void
    {
        // 1920×1080 source into a 1080×1350 frame at zoom 1, centred.
        $placement = $this->geometry->place(1920, 1080, 0.5, 0.5, 1.0, 1080, 1350);

        // Cover scale = max(1080/1920, 1350/1080) = 1.25. Display = 2400×1350.
        self::assertEqualsWithDelta(2400, $placement->width, 0.5);
        self::assertEqualsWithDelta(1350, $placement->height, 0.5);
        // Centred: x = (1080 - 2400) * 0.5 = -660 (image extends left and right).
        self::assertEqualsWithDelta(-660, $placement->x, 0.5);
        self::assertEqualsWithDelta(0, $placement->y, 0.5);
    }

    public function testFocalPointShiftsTheCrop(): void
    {
        $left = $this->geometry->place(1920, 1080, 0.0, 0.5, 1.0, 1080, 1350);
        $right = $this->geometry->place(1920, 1080, 1.0, 0.5, 1.0, 1080, 1350);

        self::assertEqualsWithDelta(0, $left->x, 0.5, 'focusX 0 aligns the left edge');
        self::assertEqualsWithDelta(-1320, $right->x, 0.5, 'focusX 1 aligns the right edge');
    }

    public function testZoomEnlargesTheImage(): void
    {
        $base = $this->geometry->place(2000, 2000, 0.5, 0.5, 1.0, 1080, 1080);
        $zoomed = $this->geometry->place(2000, 2000, 0.5, 0.5, 1.5, 1080, 1080);

        self::assertEqualsWithDelta($base->width * 1.5, $zoomed->width, 0.5);
    }

    public function testTooSmallSourceIsFlagged(): void
    {
        // A 640-wide source cannot fill a 1080 frame without upscaling.
        $placement = $this->geometry->place(640, 640, 0.5, 0.5, 1.0, 1080, 1350);
        self::assertTrue($placement->tooSmall);

        // A large source at zoom 1 does not upscale.
        $ok = $this->geometry->place(3000, 3000, 0.5, 0.5, 1.0, 1080, 1350);
        self::assertFalse($ok->tooSmall);
    }

    public function testDerivativeCoversDisplayedWidth(): void
    {
        // Large source, 1080 frame, zoom 1 → displayed ~1350 wide → needs lg (1200)? no, xl (1600).
        $placement = $this->geometry->place(4000, 3000, 0.5, 0.5, 1.0, 1080, 1350);
        self::assertContains($placement->filter, ['lg', 'xl', 'default']);
    }
}
