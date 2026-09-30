<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service;

use DateTime;
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

    public function testFormatWritesTheEditorialWallClockWithItsOffset(): void
    {
        $utc = new DateTime('2026-09-30 14:00:00 UTC');

        self::assertSame('2026-09-30 16:00+02:00', new EditorialTimezone('Europe/Paris')->format($utc));
        self::assertSame('2026-09-30 14:00+00:00', new EditorialTimezone('UTC')->format($utc));
    }

    public function testUnsetFallsBackToTheServerTimezone(): void
    {
        self::assertSame(date_default_timezone_get(), new EditorialTimezone()->timezone->getName());
    }
}
