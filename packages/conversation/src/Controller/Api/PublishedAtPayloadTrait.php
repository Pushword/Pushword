<?php

declare(strict_types=1);

namespace Pushword\Conversation\Controller\Api;

use DateMalformedStringException;
use DateTime;
use Pushword\Conversation\Entity\Message;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

trait PublishedAtPayloadTrait
{
    /**
     * Omitted or null leaves the stored date as is. A value PHP cannot read as a
     * date comes back as a violation, so the client gets a 422 rather than a 200
     * that wrote nothing.
     *
     * @param array<string, mixed> $data
     */
    private function applyPublishedAt(Message $message, array $data): ConstraintViolationList
    {
        $value = $data['publishedAt'] ?? null;
        if (null === $value) {
            return new ConstraintViolationList();
        }

        $publishedAt = \is_string($value) ? self::parseDateTime($value) : null;
        if (null !== $publishedAt) {
            $message->publishedAt = $publishedAt;

            return new ConstraintViolationList();
        }

        return new ConstraintViolationList([new ConstraintViolation(
            message: \sprintf('Unreadable date %s: send an ISO 8601 date-time, e.g. "2026-09-15T10:00:00+02:00".', json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)),
            messageTemplate: null,
            parameters: [],
            root: $message,
            propertyPath: 'publishedAt',
            invalidValue: $value,
        )]);
    }

    /**
     * Mutable on purpose: the column is DATETIME_MUTABLE, and Doctrine rejects a
     * DateTimeImmutable at flush.
     */
    private static function parseDateTime(string $value): ?DateTime
    {
        // PHP reads a blank string as "now": a client clearing the field would
        // publish at the time of the call.
        if ('' === trim($value)) {
            return null;
        }

        try {
            return new DateTime($value);
        } catch (DateMalformedStringException) {
            return null;
        }
    }
}
