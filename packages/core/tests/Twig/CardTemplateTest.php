<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

#[Group('integration')]
final class CardTemplateTest extends KernelTestCase
{
    /** @param array<string, mixed> $params what the test varies, over a plain linked title */
    private function renderCard(array $params): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('@PushwordCore/component/card.html.twig', $params + [
            'title' => 'A title',
            'link' => '/the-title-link',
            // strict_variables is on and the template has no default for it.
            'image' => null,
        ]);
    }

    /**
     * The classes on the card itself — `clickable` reads as a substring of the marker the
     * title carries, so it has to be looked for in the box's own class list.
     *
     * @return string[]
     */
    private function cardClasses(string $html): array
    {
        self::assertSame(1, preg_match('/<article[^>]*class="([^"]*)"/', $html, $matches));

        return explode(' ', $matches[1]);
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
            'description' => 'Read [the description link](/the-description-link) too.',
            // The card stays clickable when its button leads where the title does, so the
            // box then holds a third link: the marker has to stay on the title alone.
            'buttonLink' => '/the-title-link',
            'buttonLinkLabel' => 'Read more',
        ]);

        self::assertContains('clickable', $this->cardClasses($html));
        self::assertStringContainsString('class="clickable-link" href="/the-title-link"', $html);
        self::assertStringContainsString('href="/the-description-link"', $html);
        self::assertSame(1, substr_count($html, 'clickable-link'));
    }

    /**
     * A button leading elsewhere than the title makes the card ambiguous, so it is not a
     * clickable box at all — and the marker, scoped under `.clickable` in the stylesheet,
     * stretches nothing there.
     */
    public function testACardWhoseButtonLeadsElsewhereIsNotAClickableBox(): void
    {
        $html = $this->renderCard([
            'buttonLink' => '/somewhere-else',
            'buttonLinkLabel' => 'Read more',
        ]);

        self::assertNotContains('clickable', $this->cardClasses($html));
        self::assertStringContainsString('link-btn', $html);
    }

    /**
     * Obfuscated, the title is a `span[data-rot]` until the uncloak listener converts
     * it — the marker has to be on the span, which stretches the same way and carries
     * its attributes over to the `<a>` it becomes.
     */
    public function testAnObfuscatedTitleCarriesTheMarkerOnTheCloakedSpan(): void
    {
        $html = $this->renderCard(['obfuscateLink' => true]);

        self::assertStringContainsString('<span class="clickable-link" data-rot=', $html);
    }
}
