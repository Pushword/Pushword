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
}
