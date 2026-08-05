<?php

namespace Pushword\PageScanner\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\PageScanner\Service\InlineDirective;

final class InlineDirectiveTest extends TestCase
{
    public function testOneCommentListsSeveralPatterns(): void
    {
        $content = "# Title\n\n<!-- page-scanner-ignore: image-alt-missing, link-* -->\n\ncontent";

        self::assertSame(['image-alt-missing', 'link-*'], InlineDirective::patterns($content, 'page-scanner-ignore'));
    }

    public function testEveryCommentCounts(): void
    {
        $content = "<!--page-scanner-ignore:date-shortcode-->\n<!-- page-scanner-ignore: twig-error -->";

        self::assertSame(['date-shortcode', 'twig-error'], InlineDirective::patterns($content, 'page-scanner-ignore'));
    }

    /**
     * A trailing comma is what a hand-written list ends up with.
     */
    public function testAnEmptyDeclarationIsNotAPatternMatchingEverything(): void
    {
        self::assertSame(['link-*'], InlineDirective::patterns('<!-- page-scanner-ignore: link-*, , -->', 'page-scanner-ignore'));
    }

    /**
     * One name is a prefix of the other, so the colon has to be part of what is
     * matched — otherwise every link a page skips would also read as an error pattern.
     */
    public function testANameIsNotReadAsThePrefixOfAnother(): void
    {
        $content = '<!-- page-scanner-ignore-link: https://flaky.example.com/* -->';

        self::assertSame([], InlineDirective::patterns($content, 'page-scanner-ignore'));
        self::assertSame(['https://flaky.example.com/*'], InlineDirective::patterns($content, 'page-scanner-ignore-link'));
    }

    public function testTheOtherDirectiveIsNotRead(): void
    {
        $content = '<!-- page-scanner-ignore: link-not-found -->';

        self::assertSame([], InlineDirective::patterns($content, 'page-scanner-ignore-link'));
    }

    public function testContentDeclaringNothing(): void
    {
        self::assertSame([], InlineDirective::patterns('# Just a title', 'page-scanner-ignore'));
    }

    /**
     * The scanner's own documentation page shows the syntax. Read from a code sample,
     * it silenced every finding it illustrates — on that page and on any quoting it.
     */
    public function testADirectiveShownInACodeSampleAsksForNothing(): void
    {
        $fenced = "# Doc\n\n```markdown\n<!-- page-scanner-ignore: image-alt-missing -->\n```\n";

        self::assertSame([], InlineDirective::patterns($fenced, 'page-scanner-ignore'));
        self::assertSame([], InlineDirective::patterns('Write `<!-- page-scanner-ignore: link-* -->` in the content.', 'page-scanner-ignore'));
    }

    /**
     * A page documenting the syntax may also be using it.
     */
    public function testADirectiveOutsideTheSampleStillCounts(): void
    {
        $content = "~~~markdown\n<!-- page-scanner-ignore: image-alt-missing -->\n~~~\n\n<!-- page-scanner-ignore: todo-comment -->";

        self::assertSame(['todo-comment'], InlineDirective::patterns($content, 'page-scanner-ignore'));
    }

    /**
     * How CommonMark reads it, and the reason worth pinning: a stray opening fence
     * makes the rest of the page a code sample, directives included.
     */
    public function testAnUnclosedFenceRunsToTheEndOfTheContent(): void
    {
        self::assertSame([], InlineDirective::patterns("```\n<!-- page-scanner-ignore: link-* -->", 'page-scanner-ignore'));
    }

    /**
     * `isWebLink()` accepts commas in a URL, so the pattern naming one has to as well —
     * a map link carries its coordinates comma-separated.
     */
    public function testAnEscapedCommaBelongsToThePattern(): void
    {
        $content = '<!-- page-scanner-ignore-link: https://maps.example/@45.1\,4.5*, https://b.example/* -->';

        self::assertSame(
            ['https://maps.example/@45.1,4.5*', 'https://b.example/*'],
            InlineDirective::patterns($content, 'page-scanner-ignore-link')
        );
    }
}
