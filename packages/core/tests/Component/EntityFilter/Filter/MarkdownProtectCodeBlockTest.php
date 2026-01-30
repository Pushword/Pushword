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
}
