<?php

declare(strict_types=1);

namespace Pushword\Core\Service;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * The timezone editors read and write dates in (flat files, API), set by
 * `pushword.editorial_timezone`. The database keeps the server's: Doctrine's
 * datetime type writes the wall clock and drops the offset.
 */
final readonly class EditorialTimezone
{
    /** The wall clock plus its offset, e.g. `2026-09-30 16:00+02:00`: the offset keeps the instant exact on the way back. */
    public const string FORMAT = 'Y-m-d H:iP';

    public DateTimeZone $timezone;

    public function __construct(?string $editorialTimezone = null)
    {
        $this->timezone = new DateTimeZone($editorialTimezone ?? date_default_timezone_get());
    }

    /**
     * An offset in the value is kept to the instant. The result is in the server
     * timezone, the only one Doctrine stores without shifting it.
     */
    public function parse(string $value): DateTime
    {
        return new DateTime($value)->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    public function format(DateTimeInterface $date, string $format = self::FORMAT): string
    {
        return DateTimeImmutable::createFromInterface($date)->setTimezone($this->timezone)->format($format);
    }
}
