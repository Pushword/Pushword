<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\Filter\MarkdownProtectInlineCode;

class MarkdownProtectInlineCodeTest extends TestCase
{
    public function testProtectAndRestoreRoundTrip(): void
    {
        $filter = new MarkdownProtectInlineCode();
        $original = 'Use the `echo` command to print output.';

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('`echo`', $protected);
        self::assertStringContainsString('___INLINE_CODE_PLACEHOLDER_', $protected);

        $restored = $filter->restore($protected);

        self::assertSame($original, $restored);
    }

    public function testNoInlineCodePassthrough(): void
    {
        $filter = new MarkdownProtectInlineCode();
        $original = 'Just plain text without any inline code.';

        $protected = $filter->protect($original);

        self::assertSame($original, $protected);

        $restored = $filter->restore($protected);
        self::assertSame($original, $restored);
    }

    public function testMultipleInlineCodes(): void
    {
        $filter = new MarkdownProtectInlineCode();
        $original = 'Use `foo` and `bar` together.';

        $protected = $filter->protect($original);

        self::assertStringNotContainsString('`foo`', $protected);
        self::assertStringNotContainsString('`bar`', $protected);

        $restored = $filter->restore($protected);

        self::assertSame($original, $restored);
    }

    public function testDoubleBacktickInlineCode(): void
    {
        $filter = new MarkdownProtectInlineCode();
        $original = 'Show ``code with `backtick` inside`` here.';

        $protected = $filter->protect($original);

        self::assertStringContainsString('___INLINE_CODE_PLACEHOLDER_', $protected);

        $restored = $filter->restore($protected);

        self::assertSame($original, $restored);
    }
}
