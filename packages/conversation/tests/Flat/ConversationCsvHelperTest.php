<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Flat;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Conversation\Flat\ConversationCsvHelper;

class ConversationCsvHelperTest extends TestCase
{
    public function testFormatDateWithNull(): void
    {
        self::assertNull(ConversationCsvHelper::formatDate(null));
    }

    public function testFormatDateWithValidDate(): void
    {
        $date = new DateTimeImmutable('2024-06-15T10:30:00+00:00');

        self::assertSame('2024-06-15T10:30:00+00:00', ConversationCsvHelper::formatDate($date));
    }

    public function testParseDateWithNull(): void
    {
        self::assertNull(ConversationCsvHelper::parseDate(null));
    }

    public function testParseDateWithEmptyString(): void
    {
        self::assertNull(ConversationCsvHelper::parseDate(''));
    }

    public function testParseDateWithWhitespace(): void
    {
        self::assertNull(ConversationCsvHelper::parseDate('   '));
    }

    public function testParseDateWithValidDate(): void
    {
        $result = ConversationCsvHelper::parseDate('2024-06-15T10:30:00+00:00');

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2024-06-15', $result->format('Y-m-d'));
    }

    public function testParseDateWithInvalidDate(): void
    {
        self::assertNull(ConversationCsvHelper::parseDate('not-a-date'));
    }

    #[DataProvider('provideDecodeValue')]
    public function testDecodeValue(string $input, mixed $expected): void
    {
        self::assertSame($expected, ConversationCsvHelper::decodeValue($input));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function provideDecodeValue(): iterable
    {
        yield 'json array' => ['[1,2,3]', [1, 2, 3]];
        yield 'json object' => ['{"key":"value"}', ['key' => 'value']];
        yield 'invalid json' => ['{invalid', '{invalid'];
        yield 'boolean true' => ['true', true];
        yield 'boolean false' => ['false', false];
        yield 'integer' => ['42', 42];
        yield 'float' => ['3.14', 3.14];
        yield 'plain string' => ['hello', 'hello'];
    }

    #[DataProvider('provideEncodeValue')]
    public function testEncodeValue(mixed $input, ?string $expected): void
    {
        self::assertSame($expected, ConversationCsvHelper::encodeValue($input));
    }

    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function provideEncodeValue(): iterable
    {
        yield 'null' => [null, null];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'integer' => [42, '42'];
        yield 'float' => [3.14, '3.14'];
        yield 'string' => ['hello', 'hello'];
        yield 'array' => [['a', 'b'], '["a","b"]'];
    }

    public function testBaseColumnsContainsExpectedKeys(): void
    {
        self::assertContains('id', ConversationCsvHelper::BASE_COLUMNS);
        self::assertContains('content', ConversationCsvHelper::BASE_COLUMNS);
        self::assertContains('createdAt', ConversationCsvHelper::BASE_COLUMNS);
    }
}
