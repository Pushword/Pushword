<?php

namespace Pushword\StaticGenerator\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Pushword\StaticGenerator\Generator\HtmlMinifier;

final class HtmlMinifierTest extends TestCase
{
    public function testCompressRemovesComments(): void
    {
        $html = '<!DOCTYPE html><html><body><!-- comment -->content</body></html>';

        $result = HtmlMinifier::compress($html);

        self::assertStringNotContainsString('<!-- comment -->', $result);
        self::assertStringContainsString('content', $result);
    }

    public function testCompressRemovesMultilineComments(): void
    {
        $html = "<!DOCTYPE html><html><body><!--\nmultiline\ncomment\n-->content</body></html>";

        $result = HtmlMinifier::compress($html);

        self::assertStringNotContainsString('multiline', $result);
        self::assertStringContainsString('content', $result);
    }

    public function testRemoveExtraWhiteSpaceCompressesWhitespace(): void
    {
        $html = '<!DOCTYPE html><html><body><p>text    with    spaces</p></body></html>';

        $result = HtmlMinifier::removeExtraWhiteSpace($html);

        self::assertStringNotContainsString('    ', $result);
    }

    public function testRemoveExtraWhiteSpacePreservesPreTags(): void
    {
        $html = "<!DOCTYPE html><html><body><pre>  preserved\n  spacing  </pre></body></html>";

        $result = HtmlMinifier::removeExtraWhiteSpace($html);

        self::assertStringContainsString('  preserved', $result);
    }

    public function testRemoveExtraWhiteSpacePreservesCodeTags(): void
    {
        $html = '<!DOCTYPE html><html><body><code>  code  content  </code></body></html>';

        $result = HtmlMinifier::removeExtraWhiteSpace($html);

        self::assertStringContainsString('  code  content  ', $result);
    }

    public function testRemoveExtraWhiteSpacePreservesSpaceBeforeInlineTag(): void
    {
        $html = "<!DOCTYPE html><html><body><p>APIDAE :\n      <strong>Structure</strong> d'abord</p></body></html>";

        $result = HtmlMinifier::removeExtraWhiteSpace($html);

        self::assertStringContainsString('APIDAE : <strong>Structure</strong>', $result);
        self::assertStringNotContainsString('APIDAE :<strong>', $result);
    }

    public function testRemoveExtraWhiteSpacePreservesSpaceBetweenInlineTags(): void
    {
        $html = "<!DOCTYPE html><html><body><p><a href=\"#\">one</a>\n  <a href=\"#\">two</a></p></body></html>";

        $result = HtmlMinifier::removeExtraWhiteSpace($html);

        self::assertStringContainsString('</a> <a', $result);
    }

    public function testRemoveExtraWhiteSpaceTightensBlockTags(): void
    {
        $html = "<!DOCTYPE html><html><body>\n  <div>\n    <p>one</p>\n    <p>two</p>\n  </div>\n</body></html>";

        $result = HtmlMinifier::removeExtraWhiteSpace($html);

        self::assertStringContainsString('</p><p>', $result);
        self::assertStringContainsString('<div><p>', $result);
        self::assertStringContainsString('</p></div>', $result);
    }

    /**
     * <pre> is placeholder-protected before the whitespace passes run, so the
     * block-tightening regexes cannot see it. A space can therefore linger right
     * before a <pre> preceded by inline content. This documents that the lingering
     * space is harmless: the <pre> content stays byte-perfect and, because <pre> is
     * block-level, a browser collapses leading whitespace before it anyway.
     */
    public function testRemoveExtraWhiteSpaceSpaceBeforePreIsHarmless(): void
    {
        // Inline content before <pre> inside a valid block parent: the space lingers
        // because <pre> is hidden behind a placeholder during the block passes.
        $html = "<!DOCTYPE html><html><body><div>see code:\n  <pre>  line 1\n  line 2  </pre></div></body></html>";

        $result = HtmlMinifier::removeExtraWhiteSpace($html);

        // <pre> content is preserved exactly, never touched by the whitespace passes.
        self::assertStringContainsString('<pre>  line 1'."\n".'  line 2  </pre>', $result);
        // A single space lingers before the <pre>; it is harmless because <pre> is a
        // block-level box, so a browser collapses leading whitespace before it anyway.
        self::assertStringContainsString('see code: <pre>', $result);
        // And the result is stable on a second pass.
        self::assertSame($result, HtmlMinifier::removeExtraWhiteSpace($result));
    }

    public function testRemoveExtraWhiteSpaceTightensWhitespaceAfterBlockBeforePre(): void
    {
        // When a block tag precedes the <pre>, its trailing space is removed as usual.
        $html = "<!DOCTYPE html><html><body><p>intro</p>\n  <pre>kept</pre></body></html>";

        $result = HtmlMinifier::removeExtraWhiteSpace($html);

        self::assertStringContainsString('</p><pre>kept</pre>', $result);
    }

    public function testNonHtmlDocumentPassthrough(): void
    {
        $html = '<div>  some   spaces  </div>';

        $result = HtmlMinifier::removeExtraWhiteSpace($html);

        self::assertSame($html, $result);
    }

    public function testCompressIsIdempotent(): void
    {
        $html = "<!DOCTYPE html><html><body>\n  <p>Hello   world</p>\n  <!-- comment -->\n  <pre>  keep  </pre>\n</body></html>";

        $first = HtmlMinifier::compress($html);
        $second = HtmlMinifier::compress($first);

        self::assertSame($first, $second);
    }

    public function testCompressPreservesMultilineClassAttributes(): void
    {
        $html = "<!DOCTYPE html><html><body><div class=\"flex items-center\n            justify-between\">content</div></body></html>";
        $result = HtmlMinifier::compress($html);
        self::assertStringContainsString('items-center', $result);
        self::assertStringContainsString('justify-between', $result);
        self::assertStringNotContainsString('items-centerjustify-between', $result);
    }

    public function testCompressLargeHtml(): void
    {
        $body = str_repeat('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>', 500);
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>'.$body.'</body></html>';

        $result = HtmlMinifier::compress($html);

        self::assertStringStartsWith('<!DOCTYPE html>', $result);
        self::assertLessThanOrEqual(\strlen($html), \strlen($result));
    }
}
