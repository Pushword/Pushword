<?php

namespace Pushword\Flat\Exporter;

use JsonException;

final class MediaCsvHelper
{
    public const array BASE_COLUMNS = ['id', 'fileName', 'alt'];

    /** @var string[] Columns exported but not imported (auto-calculated from media file) */
    public const array DIMENSION_COLUMNS = ['width', 'height', 'ratio'];

    /** @var string[] Columns that should not be imported (read-only) */
    public const array READ_ONLY_COLUMNS = ['id', 'width', 'height', 'ratio'];

    public const string ALT_COLUMN_PREFIX = 'alt_';

    public static function decodeValue(string $value): mixed
    {
        $firstChar = $value[0] ?? '';
        if (in_array($firstChar, ['[', '{'], true)) {
            try {
                return json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $value;
            }
        }

        if (in_array($value, ['true', 'false'], true)) {
            return 'true' === $value;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    public static function encodeValue(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        try {
            return json_encode($value, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Check if a column name is for a localized alt.
     */
    public static function isAltColumn(string $column): bool
    {
        return str_starts_with($column, self::ALT_COLUMN_PREFIX);
    }

    /**
     * Extract locale from alt column name.
     */
    public static function getLocaleFromAltColumn(string $column): string
    {
        return substr($column, \strlen(self::ALT_COLUMN_PREFIX));
    }

    /**
     * Build alt column name from locale.
     */
    public static function buildAltColumnName(string $locale): string
    {
        return self::ALT_COLUMN_PREFIX.$locale;
    }
}
