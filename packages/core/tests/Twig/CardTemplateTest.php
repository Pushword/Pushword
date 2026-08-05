<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

#[Group('integration')]
final class CardTemplateTest extends KernelTestCase
{
    private function getTwig(): Environment
    {
        self::bootKernel();

        /** @var Environment */
        return self::getContainer()->get('twig');
    }

    /** @param array<string, mixed> $params */
    private function renderCard(array $params): string
    {
        return $this->getTwig()->render('@PushwordCore/component/card.html.twig', $params + [
            'image' => null,
            'buttonLink' => null,
            'description' => null,
            'obfuscateLink' => false,
        ]);
    }

    /**
     * A `.clickable` box follows the link marked `.clickable-link` and no other, because
     * every stretched link covers the whole box and the hit test keeps the last one.
     * A card whose description holds a link — any markdown link — was followed to that
     * one from everywhere, its own title included.
     */
    public function testTheClickableCardFollowsItsTitleLinkAndNotTheDescriptionOne(): void
    {
        $html = $this->renderCard([
            'title' => 'A title',
            'link' => '/the-title-link',
            'description' => 'Read [the description link](/the-description-link) too.',
        ]);

        self::assertStringContainsString('clickable', $html);
        self::assertStringContainsString('class="clickable-link" href="/the-title-link"', $html);
        self::assertSame(1, substr_count($html, 'clickable-link'));
    }

    /**
     * Obfuscated, the title is a `span[data-rot]` until the uncloak listener converts
     * it — the marker has to be on the span, which stretches the same way and carries
     * its attributes over to the `<a>` it becomes.
     */
    public function testAnObfuscatedTitleCarriesTheMarkerOnTheCloakedSpan(): void
    {
        $html = $this->renderCard([
            'title' => 'A title',
            'link' => '/the-title-link',
            'obfuscateLink' => true,
        ]);

        self::assertStringContainsString('<span class="clickable-link" data-rot=', $html);
    }
}
