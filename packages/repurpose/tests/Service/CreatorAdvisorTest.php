<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Model\Carousel;
use Pushword\Repurpose\Model\Creator;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\CreatorAdvisor;
use Pushword\Repurpose\Service\CreatorResolverInterface;

#[Group('integration')]
final class CreatorAdvisorTest extends TestCase
{
    /**
     * @param array<string, string> $known
     */
    private function advisor(array $known, ?Creator $fallback): CreatorAdvisor
    {
        return new CreatorAdvisor(new readonly class($known, $fallback) implements CreatorResolverInterface {
            /**
             * @param array<string, string> $known
             */
            public function __construct(
                private array $known,
                private ?Creator $fallback,
            ) {
            }

            public function resolve(Carousel $carousel, string $host): ?Creator
            {
                return $this->fallback;
            }

            public function available(string $host): array
            {
                return $this->known;
            }
        });
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

    public function testUnknownKeyWarnsWithTheFallbackAndTheKnownKeys(): void
    {
        $warnings = $this->advisor(['robin' => 'Robin'], new Creator('Pushword', type: 'business'))
            ->warnings($this->carousel('jane'), 'x.example');

        self::assertCount(1, $warnings);
        self::assertSame('creator', $warnings[0]['path']);
        self::assertStringContainsString('"jane"', $warnings[0]['message']);
        self::assertStringContainsString('Pushword', $warnings[0]['message']);
        self::assertStringContainsString('robin', $warnings[0]['message']);
    }

    public function testEmptyRegistrySaysSoInsteadOfListingNothing(): void
    {
        $warnings = $this->advisor([], null)->warnings($this->carousel('jane'), 'x.example');

        self::assertCount(1, $warnings);
        self::assertStringContainsString('(none configured)', $warnings[0]['message']);
        self::assertStringContainsString('no byline will be shown', $warnings[0]['message']);
    }

    public function testKnownKeyInlineObjectAndDisabledBylineStaySilent(): void
    {
        $advisor = $this->advisor(['robin' => 'Robin'], null);

        self::assertSame([], $advisor->warnings($this->carousel('robin'), 'x.example'));
        self::assertSame([], $advisor->warnings($this->carousel(['name' => 'Jane']), 'x.example'));
        self::assertSame([], $advisor->warnings($this->carousel(null), 'x.example'));
        // `creatorOnSlides: none` renders no byline at all — nothing to warn about.
        self::assertSame([], $advisor->warnings($this->carousel('jane', 'none'), 'x.example'));
    }
}
