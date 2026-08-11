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

    public function testLabelOwningAComponentRendersThroughIt(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!faq] Can luggage be carried?\n>\n> Wherever a road serves the night stop.");

        self::assertStringContainsString('https://schema.org/Question', $html);
        self::assertStringContainsString('<h3 itemprop="name">Can luggage be carried?</h3>', $html);
        self::assertStringContainsString('<p>Wherever a road serves the night stop.</p>', $html);
        self::assertStringNotContainsString('notice-faq', $html);
    }

    public function testLabelWithoutAComponentFallsBackToTheGenericNotice(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!faqq] Typo in the label\n> body");

        self::assertStringContainsString('notice notice-faqq', $html);
    }

    public function testAttributesOtherThanIdAndClassReachTheComponent(): void
    {
        $html = $this->getMarkdownParser()->transform("{#luggage .compact tag=\"h2\"}\n> [!faq] Can luggage be carried?\n> Yes.");

        self::assertStringContainsString('<h2 itemprop="name" id="luggage">', $html);
        self::assertStringContainsString('class="faq compact"', $html);
    }

    public function testAttributesClosingTheMarkerLineApply(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!faq] Can luggage be carried? {#luggage}\n> Yes.");

        self::assertStringContainsString('<h3 itemprop="name" id="luggage">Can luggage be carried?</h3>', $html);
        self::assertStringNotContainsString('{#luggage}', $html);
    }

    public function testBothAttributeFormsCombine(): void
    {
        $html = $this->getMarkdownParser()->transform("{#luggage}\n> [!faq] Can luggage be carried? {.compact tag=\"h2\"}\n> Yes.");

        self::assertStringContainsString('<h2 itemprop="name" id="luggage">', $html);
        self::assertStringContainsString('class="faq compact"', $html);
    }

    public function testAMarkerLineCarryingOnlyAttributesKeepsTheLabelAsTitle(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!note] {#anchor}\n> body");

        self::assertStringContainsString('id="anchor"', $html);
        self::assertStringContainsString('>Note</p>', $html);
    }

    public function testATwigCommentClosingTheTitleIsNotReadAsAttributes(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!note] Titled {# a comment #}\n> body");

        self::assertStringContainsString('>Titled {# a comment #}</p>', $html);
    }

    public function testATitleEndingOnBracesThatAreNotAttributesStaysLiteral(): void
    {
        $html = $this->getMarkdownParser()->transform("> [!note] Reading {some placeholder}\n> body");

        self::assertStringContainsString('>Reading {some placeholder}</p>', $html);
    }

    public function testInlineConverterNeverBuildsANotice(): void
    {
        $html = $this->getMarkdownParser()->transformInline('> [!warning] Version');

        self::assertStringNotContainsString('notice', $html);
    }
}
