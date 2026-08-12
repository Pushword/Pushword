<?php

namespace Pushword\PageScanner\Tests\Scanner;

use PHPUnit\Framework\TestCase;
use Pushword\PageScanner\Scanner\ScanErrorCode;

final class ScanErrorCodeTest extends TestCase
{
    /**
     * A code is what a site writes its `errors_to_ignore` against, what a page writes
     * in its `page-scanner-ignore` comment, and what the result cache stores. Renaming
     * one silently un-ignores findings everywhere it was relied on, so the released set
     * is pinned here: a new code is one line to add, changing one is a decision to take
     * with an upgrade note.
     *
     * Keep this list and the table in `docs/content/extension/page-scanner.md` together.
     */
    public function testTheReleasedCodesAreFrozen(): void
    {
        $codes = array_column(ScanErrorCode::cases(), 'value');
        sort($codes);

        self::assertSame([
            'date-shortcode',
            'image-alt-missing',
            'image-derivative-broken',
            'image-not-found',
            'link-anchor',
            'link-empty',
            'link-mailto',
            'link-noindex',
            'link-not-found',
            'link-not-published',
            'link-redirection',
            'link-relative',
            'link-status',
            'link-unreachable',
            'parent-host',
            'render-error',
            'todo-do-when-published',
            'todo-link-when-published',
            'todo-unknown-page',
            'translation-duplicate-locale',
            'translation-same-locale',
            'twig-error',
        ], $codes);
    }

    /**
     * Prefixes are the unit an ignore rule works on — `link-*` has to keep covering
     * every link finding, so a new one cannot invent its own vocabulary.
     */
    public function testEveryCodeBelongsToAKnownFamily(): void
    {
        foreach (ScanErrorCode::cases() as $case) {
            self::assertMatchesRegularExpression(
                '/^(link|image|todo|translation|date|parent|render|twig)-[a-z-]+$/',
                $case->value,
            );
        }
    }
}
