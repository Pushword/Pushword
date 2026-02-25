<?php

namespace Pushword\StaticGenerator\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Pushword\StaticGenerator\Generator\HtmlMinifier;

class HtmlMinifierTest extends TestCase
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
