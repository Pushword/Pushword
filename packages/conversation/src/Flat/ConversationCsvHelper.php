<?php

namespace Pushword\Conversation\Flat;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use JsonException;

final class ConversationCsvHelper
{
    public const array BASE_COLUMNS = [
        'id',
        'type',
        'host',
        'referring',
        'content',
        'authorName',
        'authorEmail',
        'authorIp',
        'tags',
        'publishedAt',
        'createdAt',
        'updatedAt',
    ];

    public static function formatDate(?DateTimeInterface $date): ?string
    {
        return null === $date ? null : $date->format(DateTimeInterface::ATOM);
    }

    public static function parseDate(?string $value): ?DateTimeImmutable
    {
        $value = null === $value ? '' : trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
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
