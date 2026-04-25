<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Pushword\Flat\Converter\PublishedAtConverter;

final class PublishedAtConverterTest extends TestCase
{
    public function testToFlatValueReturnsDateFormatted(): void
    {
        $date = new DateTime('2024-06-15 14:30:00');
        $result = PublishedAtConverter::toFlatValue($date);

        self::assertSame('2024-06-15 14:30', $result);
    }

    public function testToFlatValueReturnsDraftForNull(): void
    {
        $result = PublishedAtConverter::toFlatValue(null);

        self::assertSame('draft', $result);
    }

    public function testFromFlatValueReturnsNullForDraft(): void
    {
        $result = PublishedAtConverter::fromFlatValue('draft');

        self::assertNull($result);
    }

    public function testFromFlatValueReturnsDateTimeForValidString(): void
    {
        $result = PublishedAtConverter::fromFlatValue('2024-06-15 14:30');

        self::assertInstanceOf(DateTimeInterface::class, $result);
        self::assertSame('2024-06-15 14:30', $result->format('Y-m-d H:i'));
    }

    public function testFromFlatValueReturnsDateTimeInterfaceAsIs(): void
    {
        $date = new DateTime('2024-06-15 14:30:00');
        $result = PublishedAtConverter::fromFlatValue($date);

        self::assertSame($date, $result);
    }

    public function testFromFlatValueReturnsNullForNonScalar(): void
    {
        $result = PublishedAtConverter::fromFlatValue(['invalid']);

        self::assertNull($result);
    }

    public function testRoundTripWithDate(): void
    {
        $original = new DateTime('2024-06-15 14:30:00');

        $exported = PublishedAtConverter::toFlatValue($original);
        $imported = PublishedAtConverter::fromFlatValue($exported);

        self::assertInstanceOf(DateTimeInterface::class, $imported);
        self::assertSame($original->format('Y-m-d H:i'), $imported->format('Y-m-d H:i'));
    }

    public function testRoundTripWithNull(): void
    {
        $exported = PublishedAtConverter::toFlatValue(null);
        $imported = PublishedAtConverter::fromFlatValue($exported);

        self::assertNull($imported);
    }
}
