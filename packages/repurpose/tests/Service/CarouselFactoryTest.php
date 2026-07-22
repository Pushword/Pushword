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
     * Images hydrate from the plural `images` list, and the legacy singular `image`
     * object is wrapped into a one-element list; empty-media slots are dropped so an
     * `{}` placeholder never surfaces as a spurious violation.
     */
    public function testImagesFromPluralListAndLegacySingularKey(): void
    {
        $factory = new CarouselFactory();
        $base = ['page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5'];

        $plural = $factory->fromArray([...$base, 'slides' => [[
            'title' => 'Split', 'imageLayout' => 'split-h',
            'images' => [['media' => 'a.jpg'], ['media' => 'b.jpg', 'zoom' => 1.4]],
        ]]])->slides[0];
        self::assertSame('split-h', $plural->imageLayout);
        self::assertCount(2, $plural->images);
        self::assertSame('b.jpg', $plural->images[1]->media);
        self::assertSame(1.4, $plural->images[1]->zoom);

        $legacy = $factory->fromArray([...$base, 'slides' => [['title' => 'Hi', 'image' => ['media' => 'photo.jpg']]]])->slides[0];
        self::assertSame('full', $legacy->imageLayout);
        self::assertCount(1, $legacy->images);
        $first = $legacy->firstImage();
        self::assertNotNull($first);
        self::assertSame('photo.jpg', $first->media);

        $emptySlot = $factory->fromArray([...$base, 'slides' => [['title' => 'Hi', 'images' => [['media' => ''], []]]]])->slides[0];
        self::assertSame([], $emptySlot->images);
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
