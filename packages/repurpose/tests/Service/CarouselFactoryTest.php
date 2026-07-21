<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Model\Creator;
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

    /**
     * `creator` may be a config key (kept as string), an inline `{name,…}` object
     * (hydrated to a one-off Creator), or absent/nameless (null → brand byline).
     */
    public function testCreatorAcceptsAKeyAnInlineObjectOrNothing(): void
    {
        $base = ['page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5', 'slides' => [['title' => 'Hi']]];
        $factory = new CarouselFactory();

        self::assertSame('robin', $factory->fromArray([...$base, 'creator' => 'robin'])->creator);
        self::assertNull($factory->fromArray($base)->creator);
        self::assertNull($factory->fromArray([...$base, 'creator' => ['role' => 'nameless']])->creator);

        $inline = $factory->fromArray([...$base, 'creator' => ['name' => 'Jane Doe', 'role' => 'Guest', 'type' => 'business']])->creator;
        self::assertInstanceOf(Creator::class, $inline);
        self::assertSame('Jane Doe', $inline->name);
        self::assertSame('Guest', $inline->role);
        self::assertNull($inline->avatar);
        self::assertSame('business', $inline->type);
    }
}
