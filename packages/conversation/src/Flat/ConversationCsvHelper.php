<?php

declare(strict_types=1);

namespace Pushword\Conversation\Flat;

use DateTime;
use DateTimeInterface;
use Exception;
use JsonException;
use Pushword\Core\Service\EditorialTimezone;

final class ConversationCsvHelper
{
    public const array BASE_COLUMNS = [
        'id',
        'uuid',
        'type',
        'host',
        'referring',
        'content',
        'authorName',
        'authorEmail',
        'authorIp',
        'tags',
        'mediaList',
        'publishedAt',
        'createdAt',
        'updatedAt',
        'deletedAt',
    ];

    public static function formatDate(?DateTimeInterface $date): ?string
    {
        return $date?->format(DateTimeInterface::ATOM);
    }

    /**
     * An offset is kept to the instant; a date without one is read in the
     * editorial timezone. Mutable, as the DATETIME_MUTABLE columns require.
     */
    public static function parseDate(?string $value, EditorialTimezone $editorialTimezone): ?DateTime
    {
        $value = null === $value ? '' : trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            return $editorialTimezone->parse($value);
        } catch (Exception) {
            return null;
        }
    }

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
}
