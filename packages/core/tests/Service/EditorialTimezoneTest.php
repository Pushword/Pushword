<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\EditorialTimezone;

final class EditorialTimezoneTest extends TestCase
{
    public function testAnOffsetIsStoredAtItsExactInstant(): void
    {
        $parsed = new EditorialTimezone('Europe/Paris')->parse('2026-09-30 14:00+02:00');

        self::assertSame(new DateTime('2026-09-30 12:00:00 UTC')->getTimestamp(), $parsed->getTimestamp());
        // Doctrine writes the wall clock without its offset: it must already be the server's.
        self::assertSame(date_default_timezone_get(), $parsed->getTimezone()->getName());
    }

    public function testADateWithoutOffsetIsReadInTheEditorialTimezone(): void
    {
        self::assertSame(
            new DateTime('2026-09-30 12:00:00 UTC')->getTimestamp(),
            new EditorialTimezone('Europe/Paris')->parse('2026-09-30 14:00')->getTimestamp(),
        );
        self::assertSame(
            new DateTime('2026-09-30 14:00:00 UTC')->getTimestamp(),
            new EditorialTimezone('UTC')->parse('2026-09-30 14:00')->getTimestamp(),
        );
    }

    public function testAnHourTheAutumnChangeRepeatsResolvesToItsFirstOccurrence(): void
    {
        $paris = new EditorialTimezone('Europe/Paris');

        // 02:30 happens at 00:30 UTC (summer time), then again at 01:30 UTC.
        self::assertSame(new DateTime('2026-10-25 00:30:00 UTC')->getTimestamp(), $paris->parse('2026-10-25 02:30')->getTimestamp());
        self::assertSame(new DateTime('2026-10-25 01:30:00 UTC')->getTimestamp(), $paris->parse('2026-10-25 02:30+01:00')->getTimestamp());
        // Either side of the repeated hour is unambiguous and left as is.
        self::assertSame(new DateTime('2026-10-24 23:30:00 UTC')->getTimestamp(), $paris->parse('2026-10-25 01:30')->getTimestamp());
        self::assertSame(new DateTime('2026-10-25 02:30:00 UTC')->getTimestamp(), $paris->parse('2026-10-25 03:30')->getTimestamp());
    }

    public function testAnHourTheSpringChangeSkipsMovesForward(): void
    {
        self::assertSame('2026-03-29 03:30+02:00', new EditorialTimezone('Europe/Paris')->format(new EditorialTimezone('Europe/Paris')->parse('2026-03-29 02:30')));
    }

    public function testBothOccurrencesOfTheRepeatedHourRoundTrip(): void
    {
        $paris = new EditorialTimezone('Europe/Paris');

        foreach (['2026-10-25 00:30:00 UTC' => '2026-10-25 02:30+02:00', '2026-10-25 01:30:00 UTC' => '2026-10-25 02:30+01:00'] as $instant => $exported) {
            self::assertSame($exported, $paris->format(new DateTime($instant)));
            self::assertSame(new DateTime($instant)->getTimestamp(), $paris->parse($exported)->getTimestamp());
        }
    }

    public function testFormatWritesTheEditorialWallClockWithItsOffset(): void
    {
        $utc = new DateTime('2026-09-30 14:00:00 UTC');

        self::assertSame('2026-09-30 16:00+02:00', new EditorialTimezone('Europe/Paris')->format($utc));
        self::assertSame('2026-09-30 14:00+00:00', new EditorialTimezone('UTC')->format($utc));
    }

    public function testFormatTakesAnotherFormat(): void
    {
        self::assertSame(
            '2026-09-30T16:00:00+02:00',
            new EditorialTimezone('Europe/Paris')->format(new DateTime('2026-09-30 14:00:00 UTC'), DateTimeInterface::ATOM),
        );
    }

    /** The date is often an entity's managed DateTime: shifting it in place would change the page. */
    public function testFormatLeavesTheGivenDateUntouched(): void
    {
        $date = new DateTime('2026-09-30 14:00:00 UTC');

        new EditorialTimezone('Europe/Paris')->format($date);

        self::assertSame('2026-09-30 14:00:00 UTC', $date->format('Y-m-d H:i:s T'));
    }

    public function testAZuluSuffixIsAnOffsetToo(): void
    {
        // What JavaScript's toISOString() sends.
        self::assertSame(
            new DateTime('2026-09-30 12:00:00 UTC')->getTimestamp(),
            new EditorialTimezone('Europe/Paris')->parse('2026-09-30T12:00:00.000Z')->getTimestamp(),
        );
    }

    public function testAnUnreadableValueThrows(): void
    {
        // The API turns this into a 422 and the flat import into a reported error.
        $this->expectException(DateMalformedStringException::class);
        new EditorialTimezone('Europe/Paris')->parse('not-a-date');
    }

    public function testUnsetFallsBackToTheServerTimezone(): void
    {
        self::assertSame(date_default_timezone_get(), new EditorialTimezone()->timezone->getName());
    }
}
