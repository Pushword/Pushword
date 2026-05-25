<?php

namespace Pushword\Search\Tests;

use PHPUnit\Framework\TestCase;
use Pushword\Search\Service\TextExtractor;

final class TextExtractorTest extends TestCase
{
    public function testStripsTagsScriptStyleAndDecodesEntities(): void
    {
        $html = '<h1>Title</h1>'
            .'<script>var leak = "secret";</script>'
            .'<style>.a { color: red }</style>'
            .'<p>Hello &amp; welcome</p>';

        self::assertSame('Title Hello & welcome', TextExtractor::toPlainText($html));
    }

    public function testCollapsesWhitespace(): void
    {
        self::assertSame('a b c', TextExtractor::toPlainText("<p>a\n\n   b\t c</p>"));
    }

    public function testKeepsCodeBlocksContent(): void
    {
        $html = '<pre><code>php bin/console pw:search:index</code></pre>';

        self::assertSame('php bin/console pw:search:index', TextExtractor::toPlainText($html));
    }
}
