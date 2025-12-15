<?php

namespace Pushword\Flat\Converter;

use DateTime;
use DateTimeInterface;

final class PublishedAtConverter
{
    public const string DRAFT_VALUE = 'draft';

    /**
     * Convert publishedAt value for export (to flat file).
     * Returns 'draft' if publishedAt is null, otherwise returns formatted date.
     */
    public static function toFlatValue(?DateTimeInterface $publishedAt): string
    {
        if (null === $publishedAt) {
            return self::DRAFT_VALUE;
        }

        return $publishedAt->format('Y-m-d H:i');
    }

    /**
     * Convert publishedAt value from import (from flat file).
     * Returns null if value is 'draft', otherwise returns DateTime.
     */
    public static function fromFlatValue(mixed $value): ?DateTimeInterface
    {
        if (self::DRAFT_VALUE === $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (\is_scalar($value)) {
            return new DateTime((string) $value);
        }

        return null;
    }
}
