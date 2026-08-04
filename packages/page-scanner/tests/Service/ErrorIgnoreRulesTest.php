<?php

namespace Pushword\PageScanner\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\PageScanner\Service\ErrorIgnoreRules;

final class ErrorIgnoreRulesTest extends TestCase
{
    public function testAGlobalPatternMatchesTheCode(): void
    {
        self::assertTrue(ErrorIgnoreRules::isIgnored(['link-not-found'], 'localhost.dev/a', 'link-not-found', '<code>/x</code> not found'));
        self::assertFalse(ErrorIgnoreRules::isIgnored(['link-not-found'], 'localhost.dev/a', 'link-relative', '<code>x</code> relative link'));
    }

    public function testAWildcardSilencesAWholeFamily(): void
    {
        self::assertTrue(ErrorIgnoreRules::isIgnored(['link-*'], 'localhost.dev/a', 'link-unreachable', 'unreachable'));
        self::assertFalse(ErrorIgnoreRules::isIgnored(['link-*'], 'localhost.dev/a', 'image-alt-missing', 'image without alternative text'));
    }

    /**
     * Message patterns predate codes and remain the way to pin one occurrence
     * rather than a whole family.
     */
    public function testAPatternStillMatchesThePlainMessage(): void
    {
        self::assertTrue(ErrorIgnoreRules::isIgnored(['*date shortcode*'], 'localhost.dev/a', 'date-shortcode', '<code>date(Y)</code> date shortcode left unresolved'));
    }

    /**
     * A message is HTML — the URL it quotes is wrapped in `<code>` — so a pattern
     * is written against what the message reads as, not against its markup.
     */
    public function testAMessagePatternIsMatchedAgainstThePlainText(): void
    {
        self::assertTrue(ErrorIgnoreRules::isIgnored(['/x not found'], 'localhost.dev/a', 'link-not-found', '<code>/x</code> not found'));
    }

    public function testARouteScopedPatternOnlyAppliesToItsRoute(): void
    {
        $patterns = ['localhost.dev/legacy-*: link-not-found'];

        self::assertTrue(ErrorIgnoreRules::isIgnored($patterns, 'localhost.dev/legacy-page', 'link-not-found', 'not found'));
        self::assertFalse(ErrorIgnoreRules::isIgnored($patterns, 'localhost.dev/about', 'link-not-found', 'not found'));
    }

    /**
     * Both halves have to match: the route alone silencing everything on it would
     * make a scoped rule a mute button for the page.
     */
    public function testARouteScopedPatternStillHasToMatchTheError(): void
    {
        $patterns = ['localhost.dev/legacy-*: link-not-found'];

        self::assertFalse(ErrorIgnoreRules::isIgnored($patterns, 'localhost.dev/legacy-page', 'image-alt-missing', 'image without alternative text'));
    }

    public function testAPagePatternMatchesTheCodeOrTheMessage(): void
    {
        self::assertTrue(ErrorIgnoreRules::matches(['link-*'], 'link-unreachable', 'unreachable'));
        self::assertTrue(ErrorIgnoreRules::matches(['*unreachable*'], 'link-unreachable', 'unreachable'));
        self::assertFalse(ErrorIgnoreRules::matches(['image-*'], 'link-unreachable', 'unreachable'));
        self::assertFalse(ErrorIgnoreRules::matches([], 'link-unreachable', 'unreachable'));
    }

    public function testAPageDeclaresItsOwnPatternsInline(): void
    {
        $page = new Page();
        $page->mainContent = "# Title\n\n<!-- page-scanner-ignore: image-alt-missing, link-unreachable -->\n\ncontent";

        self::assertSame(['image-alt-missing', 'link-unreachable'], ErrorIgnoreRules::forPage($page));
    }

    public function testAPageDeclaresItsOwnPatternsAsACustomProperty(): void
    {
        $page = new Page();
        $page->setCustomProperty(ErrorIgnoreRules::PAGE_PROPERTY, ['todo-*']);

        self::assertSame(['todo-*'], ErrorIgnoreRules::forPage($page));
    }

    /**
     * The property is hand-written YAML: a single pattern reads naturally as a
     * scalar, and blowing up the whole scan over that shape would be absurd.
     */
    public function testTheCustomPropertyAcceptsASinglePattern(): void
    {
        $page = new Page();
        $page->setCustomProperty(ErrorIgnoreRules::PAGE_PROPERTY, 'render-error');

        self::assertSame(['render-error'], ErrorIgnoreRules::forPage($page));
    }

    public function testTheTwoSourcesAddUp(): void
    {
        $page = new Page();
        $page->setCustomProperty(ErrorIgnoreRules::PAGE_PROPERTY, ['todo-*']);
        $page->mainContent = '<!-- page-scanner-ignore: render-error -->';

        self::assertSame(['todo-*', 'render-error'], ErrorIgnoreRules::forPage($page));
    }

    public function testAPageDeclaringNothingIgnoresNothing(): void
    {
        self::assertSame([], ErrorIgnoreRules::forPage(new Page()));
    }
}
