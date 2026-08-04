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
        self::assertTrue(ErrorIgnoreRules::isIgnored(['link-*'], 'localhost.dev/a', 'link-external', 'unreachable'));
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

    public function testARouteScopedPatternOnlyAppliesToItsRoute(): void
    {
        $patterns = ['localhost.dev/legacy-*: link-not-found'];

        self::assertTrue(ErrorIgnoreRules::isIgnored($patterns, 'localhost.dev/legacy-page', 'link-not-found', 'not found'));
        self::assertFalse(ErrorIgnoreRules::isIgnored($patterns, 'localhost.dev/about', 'link-not-found', 'not found'));
    }

    public function testAPageDeclaresItsOwnPatternsInline(): void
    {
        $page = new Page();
        $page->mainContent = "# Title\n\n<!-- page-scanner-ignore: image-alt-missing, link-external -->\n\ncontent";

        self::assertSame(['image-alt-missing', 'link-external'], ErrorIgnoreRules::forPage($page));
    }

    public function testEveryInlineDeclarationCounts(): void
    {
        $page = new Page();
        $page->mainContent = "<!--page-scanner-ignore:date-shortcode-->\n<!-- page-scanner-ignore: link-* -->";

        self::assertSame(['date-shortcode', 'link-*'], ErrorIgnoreRules::forPage($page));
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

    public function testAPageDeclaringNothingIgnoresNothing(): void
    {
        self::assertSame([], ErrorIgnoreRules::forPage(new Page()));
    }
}
