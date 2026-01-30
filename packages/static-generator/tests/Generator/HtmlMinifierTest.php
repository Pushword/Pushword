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
}
