<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\CarouselFactory;

#[Group('integration')]
final class CarouselFactoryTest extends TestCase
{
    /**
     * @param array<string, mixed> $slide
     */
    private function overlayOf(array $slide): float
    {
        $carousel = new CarouselFactory()->fromArray([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [$slide],
        ]);

        return $carousel->slides[0]->overlay;
    }

    /**
     * The legibility-safe default: an image slide with no stated overlay gets
     * 0.35; an explicit 0 is honoured; a plain colour slide stays at 0.
     */
    public function testOverlayDefaultsSafeOverAnImage(): void
    {
        self::assertSame(0.35, $this->overlayOf(['title' => 'Hi', 'image' => ['media' => 'photo.jpg']]));
        self::assertSame(0.0, $this->overlayOf(['title' => 'Hi', 'image' => ['media' => 'photo.jpg'], 'overlay' => 0]));
        self::assertSame(0.0, $this->overlayOf(['title' => 'Hi']));
    }
}
