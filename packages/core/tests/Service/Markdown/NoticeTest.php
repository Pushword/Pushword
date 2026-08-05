<?php

namespace Pushword\Core\Tests\Service\Markdown;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Notices: `> [!label]` opens a blockquote rendered through the notice
 * component; anything else stays an ordinary blockquote.
 */
#[Group('integration')]
final class NoticeTest extends KernelTestCase
{
    private function getMarkdownParser(): MarkdownParser
    {
        self::bootKernel();

        return self::getContainer()->get(MarkdownParser::class);
    }

    public function testLabelAndTitle(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!warning] Version\n>\n> Last updated: August 2026.");

        self::assertStringContainsString('notice notice-warning', $html);
        self::assertStringContainsString('>Version</p>', $html);
        self::assertStringContainsString('<p>Last updated: August 2026.</p>', $html);
        self::assertStringNotContainsString('[!warning]', $html);
    }

    public function testLabelIsCaseInsensitive(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!WARNING] Version\n> Last updated.");

        self::assertStringContainsString('notice notice-warning', $html);
    }

    public function testTitleFallsBackToTheLabel(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!note]\n> Just a remark.");

        self::assertStringContainsString('>Note</p>', $html);
        self::assertStringContainsString('<p>Just a remark.</p>', $html);
    }

    public function testUnknownLabelKeepsItsOwnClass(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!sponsored]\n> Paid content.");

        self::assertStringContainsString('notice notice-sponsored', $html);
        self::assertStringContainsString('>Sponsored</p>', $html);
    }

    public function testAttributesLineAboveTheNoticeApplies(): void
    {
        $html = $this->getMarkdownParser()->transform("{#disclosure .text-sm}\n> [!note] Titled\n> body");

        self::assertStringContainsString('id="disclosure"', $html);
        self::assertStringContainsString('text-sm', $html);
    }

    public function testBodyIsParsedAsMarkdown(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!tip] Shortcuts\n>\n> - one\n> - two\n>\n> See [the docs](/docs).");

        self::assertStringContainsString('<li>one</li>', $html);
        self::assertStringContainsString('<a href="/docs">the docs</a>', $html);
    }

    public function testPlainBlockquoteIsUntouched(): void
    {
        $html = $this->getMarkdownParser()->transform('> Just a quote.');

        self::assertStringContainsString('<blockquote>', $html);
        self::assertStringNotContainsString('notice', $html);
    }

    public function testQuoteOpeningOnALinkedImageIsNotAMarker(): void
    {
        $html = $this->getMarkdownParser()->transform("> [![alt](missing.jpg)](/page)\n> A caption.");

        self::assertStringContainsString('<blockquote>', $html);
        self::assertStringNotContainsString('notice', $html);
    }

    public function testMarkerMustOpenTheQuote(): void
    {
        $html = $this->getMarkdownParser()->transform("> A quotation.\n> [!note] not a marker here");

        self::assertStringContainsString('<blockquote>', $html);
        self::assertStringNotContainsString('notice', $html);
    }

    public function testTitleWithoutASpaceIsNotAMarker(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!note]Titled\n> body");

        self::assertStringContainsString('<blockquote>', $html);
        self::assertStringNotContainsString('notice', $html);
    }

    public function testTitleIsEscaped(): void
    {
        $html = $this->getMarkdownParser()->transform('> [!note] <script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testEscapedMarkerStaysLiteral(): void
    {
        $html = $this->getMarkdownParser()->transform("> \\[!NOTE]\n> Quoting the syntax.");

        self::assertStringContainsString('<blockquote>', $html);
        self::assertStringContainsString('[!NOTE]', $html);
    }

    public function testInlineConverterNeverBuildsANotice(): void
    {
        $html = $this->getMarkdownParser()->transformInline('> [!warning] Version');

        self::assertStringNotContainsString('notice', $html);
    }
}
