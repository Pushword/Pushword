<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Repurpose\Model\Carousel;
use Pushword\Repurpose\Model\Creator;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\CreatorResolverInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Against the dev-app site config, whose default app declares
 * `repurpose_creators: {robin: …}` — the demo data the studio picker shows.
 */
#[Group('integration')]
final class ConfigCreatorResolverTest extends KernelTestCase
{
    private function resolver(): CreatorResolverInterface
    {
        self::bootKernel();

        return self::getContainer()->get(CreatorResolverInterface::class);
    }

    /**
     * @param string|array<string, string>|null $creator
     */
    private function carousel(string|array|null $creator, string $onSlides = 'intro-outro'): Carousel
    {
        return new CarouselFactory()->fromArray([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'creator' => $creator, 'creatorOnSlides' => $onSlides,
            'slides' => [['title' => 'Hi']],
        ]);
    }

    public function testAvailableListsTheConfiguredCreatorsByDisplayName(): void
    {
        self::assertSame(['robin' => 'Robin'], $this->resolver()->available('localhost.dev'));
    }

    public function testResolveHonoursKeyInlineObjectAndBrandFallback(): void
    {
        $resolver = $this->resolver();

        $robin = $resolver->resolve($this->carousel('robin'), 'localhost.dev');
        self::assertInstanceOf(Creator::class, $robin);
        self::assertSame('Robin', $robin->name);
        self::assertSame('Founder at Pushword', $robin->role);

        // An inline creator bypasses the config registry entirely.
        $inline = $resolver->resolve($this->carousel(['name' => 'Jane Doe']), 'localhost.dev');
        self::assertSame('Jane Doe', $inline?->name);

        // An unknown key falls back to the brand (site name, business type).
        $brand = $resolver->resolve($this->carousel('nobody'), 'localhost.dev');
        self::assertInstanceOf(Creator::class, $brand);
        self::assertSame('Pushword', $brand->name);
        self::assertSame('business', $brand->type);

        self::assertNull($resolver->resolve($this->carousel('robin', 'none'), 'localhost.dev'));
    }
}
