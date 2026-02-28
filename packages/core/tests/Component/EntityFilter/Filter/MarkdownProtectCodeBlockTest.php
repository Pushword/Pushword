<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\Filter\MarkdownProtectCodeBlock;

class MarkdownProtectCodeBlockTest extends TestCase
{
    public function testProtectAndRestoreRoundTrip(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "Some text\n\n```php\necho 'hello';\n```\n\nMore text";

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('```php', $protected);
        self::assertStringContainsString('___CODE_BLOCK_PLACEHOLDER_', $protected);

        $restored = $filter->restore([$protected]);

        self::assertStringContainsString("```php\necho 'hello';\n```", $restored[0]);
    }

    public function testNoCodeBlocksPassthrough(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = 'Just a simple paragraph with no code.';

        $protected = $filter->protect($original);

        self::assertSame($original, $protected);

        $restored = $filter->restore([$protected]);
        self::assertSame([$original], $restored);
    }

    public function testMultipleCodeBlocks(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "First\n\n```js\nlet x = 1;\n```\n\nMiddle\n\n```css\nbody {}\n```\n\nEnd";

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('```js', $protected);
        self::assertStringNotContainsString('```css', $protected);
        self::assertStringContainsString('___CODE_BLOCK_PLACEHOLDER_0___', $protected);
        self::assertStringContainsString('___CODE_BLOCK_PLACEHOLDER_1___', $protected);

        $restored = $filter->restore([$protected]);

        self::assertStringContainsString("```js\nlet x = 1;\n```", $restored[0]);
        self::assertStringContainsString("```css\nbody {}\n```", $restored[0]);
    }

    public function testPreBlockWithBlankLines(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "Before\n\n<pre>\nline 1\n\nline 3\n</pre>\n\nAfter";

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('<pre>', $protected);
        self::assertStringContainsString('___CODE_BLOCK_PLACEHOLDER_', $protected);

        $parts = explode("\n\n", $protected);
        $restored = $filter->restore($parts);
        $result = implode("\n\n", $restored);

        self::assertStringContainsString("<pre>\nline 1\n\nline 3\n</pre>", $result);
    }

    public function testPreBlockWithAttributes(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "Text\n\n<pre class=\"language-php\">\ncode here\n\nmore code\n</pre>\n\nEnd";

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('<pre class', $protected);

        $parts = explode("\n\n", $protected);
        $restored = $filter->restore($parts);
        $result = implode("\n\n", $restored);

        self::assertStringContainsString("<pre class=\"language-php\">\ncode here\n\nmore code\n</pre>", $result);
    }

    public function testMultiplePreBlocks(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "<pre>\nblock 1\n\nblank\n</pre>\n\nMiddle\n\n<pre>\nblock 2\n\nblank\n</pre>";

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('<pre>', $protected);

        $parts = explode("\n\n", $protected);
        $restored = $filter->restore($parts);
        $result = implode("\n\n", $restored);

        self::assertStringContainsString("<pre>\nblock 1\n\nblank\n</pre>", $result);
        self::assertStringContainsString("<pre>\nblock 2\n\nblank\n</pre>", $result);
    }

    public function testMixedFencedAndPreBlocks(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "```php\necho 1;\n```\n\nText\n\n<pre>\nline\n\nblank\n</pre>\n\nEnd";

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('```php', $protected);
        self::assertStringNotContainsString('<pre>', $protected);
        self::assertStringContainsString('___CODE_BLOCK_PLACEHOLDER_0___', $protected);
        self::assertStringContainsString('___CODE_BLOCK_PLACEHOLDER_1___', $protected);

        $parts = explode("\n\n", $protected);
        $restored = $filter->restore($parts);
        $result = implode("\n\n", $restored);

        self::assertStringContainsString("```php\necho 1;\n```", $result);
        self::assertStringContainsString("<pre>\nline\n\nblank\n</pre>", $result);
    }

    public function testPreBlockWithMultipleConsecutiveBlankLines(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "Before\n\n<pre>\nline 1\n\n\n\nline 5\n</pre>\n\nAfter";

        $protected = $filter->protect($original);

        $parts = explode("\n\n", $protected);
        $restored = $filter->restore($parts);
        $result = implode("\n\n", $restored);

        self::assertStringContainsString("<pre>\nline 1\n\n\n\nline 5\n</pre>", $result);
    }

    public function testPreBlockAtStartOfContent(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "<pre>\ncode\n\nblank\n</pre>\n\nAfter";

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('<pre>', $protected);

        $parts = explode("\n\n", $protected);
        $restored = $filter->restore($parts);
        $result = implode("\n\n", $restored);

        self::assertStringContainsString("<pre>\ncode\n\nblank\n</pre>", $result);
    }

    public function testPreBlockAtEndOfContent(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "Before\n\n<pre>\ncode\n\nblank\n</pre>";

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('<pre>', $protected);

        $parts = explode("\n\n", $protected);
        $restored = $filter->restore($parts);
        $result = implode("\n\n", $restored);

        self::assertStringContainsString("<pre>\ncode\n\nblank\n</pre>", $result);
    }

    public function testNestedPreCode(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "Text\n\n<pre><code>\nfunction() {\n\n  return true;\n}\n</code></pre>\n\nEnd";

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('<pre>', $protected);

        $parts = explode("\n\n", $protected);
        $restored = $filter->restore($parts);
        $result = implode("\n\n", $restored);

        self::assertStringContainsString("<pre><code>\nfunction() {\n\n  return true;\n}\n</code></pre>", $result);
    }

    public function testPreWithoutBlankLinesStillProtected(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "Before\n\n<pre>\nsingle line\n</pre>\n\nAfter";

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('<pre>', $protected);

        $parts = explode("\n\n", $protected);
        $restored = $filter->restore($parts);
        $result = implode("\n\n", $restored);

        self::assertStringContainsString("<pre>\nsingle line\n</pre>", $result);
    }

    public function testInlinePreNotCaptured(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "Some text with <pre>inline</pre> content.";

        $protected = $filter->protect($original);

        self::assertStringContainsString('<pre>inline</pre>', $protected);
    }

    public function testUnclosedPreNotCaptured(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "Before\n\n<pre>\nsome code\n\nmore code\n\nAfter";

        $protected = $filter->protect($original);

        self::assertSame($original, $protected);
    }

    /**
     * Nested <pre> is invalid HTML. The non-greedy regex matches from the
     * outer <pre> to the first </pre>, leaving the second </pre> orphaned.
     * This is acceptable — authors should not nest <pre> tags.
     */
    public function testNestedPreMatchesToFirstClose(): void
    {
        $filter = new MarkdownProtectCodeBlock();
        $original = "Before\n\n<pre>\nouter\n\n<pre>\ninner\n</pre>\n\nstill outer\n</pre>\n\nAfter";

        $protected = $filter->protect($original);

        // Matches from first <pre> to first </pre>, orphaning the rest
        self::assertStringContainsString('___CODE_BLOCK_PLACEHOLDER_', $protected);
        self::assertStringContainsString('still outer', $protected);
        self::assertStringContainsString('</pre>', $protected);
    }
}
