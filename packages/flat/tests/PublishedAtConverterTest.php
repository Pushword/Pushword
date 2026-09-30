<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Flat\Converter\PublishedAtConverter;

final class PublishedAtConverterTest extends TestCase
{
    private PublishedAtConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new PublishedAtConverter(new EditorialTimezone('Europe/Paris'));
    }

    public function testToFlatValueWritesTheEditorialTimeWithItsOffset(): void
    {
        $result = $this->converter->toFlatValue(new DateTime('2026-09-30 14:00:00 UTC'));

        self::assertSame('2026-09-30 16:00+02:00', $result);
    }

    public function testToFlatValueReturnsDraftForNull(): void
    {
        $result = $this->converter->toFlatValue(null);

        self::assertSame('draft', $result);
    }

    public function testFromFlatValueReturnsNullForDraft(): void
    {
        $result = $this->converter->fromFlatValue('draft');

        self::assertNull($result);
    }

    public function testFromFlatValueKeepsTheInstantOfAnOffset(): void
    {
        $result = $this->converter->fromFlatValue('2026-09-30 16:00+02:00');

        self::assertInstanceOf(DateTimeInterface::class, $result);
        self::assertSame(new DateTime('2026-09-30 14:00:00 UTC')->getTimestamp(), $result->getTimestamp());
    }

    public function testFromFlatValueReturnsDateTimeInterfaceAsIs(): void
    {
        $date = new DateTime('2024-06-15 14:30:00');
        $result = $this->converter->fromFlatValue($date);

        self::assertSame($date, $result);
    }

    public function testFromFlatValueReturnsNullForNonScalar(): void
    {
        $result = $this->converter->fromFlatValue(['invalid']);

        self::assertNull($result);
    }

    public function testRoundTripKeepsTheTimestamp(): void
    {
        $original = new DateTime('2026-09-30 14:00:00 UTC');

        $imported = $this->converter->fromFlatValue($this->converter->toFlatValue($original));

        self::assertInstanceOf(DateTimeInterface::class, $imported);
        self::assertSame($original->getTimestamp(), $imported->getTimestamp());
    }

    public function testRoundTripWithNull(): void
    {
        $exported = $this->converter->toFlatValue(null);
        $imported = $this->converter->fromFlatValue($exported);

        self::assertNull($imported);
    }
}
