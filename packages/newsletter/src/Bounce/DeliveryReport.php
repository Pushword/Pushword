<?php

namespace Pushword\Newsletter\Bounce;

/**
 * A bounce, read the way RFC 3464 writes it rather than guessed from prose.
 *
 * A mail server that gives up returns a `multipart/report` carrying a
 * `message/delivery-status` part, which names every address it could not
 * deliver to and says whether the refusal is final. That part is identical
 * everywhere; the human-readable part next to it is written for a person, in
 * the remote server's language and layout, and is never parsed here.
 *
 * Only permanent failures (5.x.x) are reported. A 4.x.x is a mailbox that was
 * full an hour ago or a server that was down, and dropping a reader over one
 * would lose an address the next retry reaches.
 */
final readonly class DeliveryReport
{
    /** @param list<FailedRecipient> $failures */
    private function __construct(public array $failures)
    {
    }

    /**
     * @return self|null null when the message is not a delivery report at all,
     *                   as opposed to a report naming no permanent failure
     */
    public static function fromRaw(string $raw): ?self
    {
        $message = str_replace("\r\n", "\n", $raw);
        [$headers, $body] = self::split($message);

        $contentType = self::field($headers, 'content-type');

        if (null === $contentType || ! str_contains(mb_strtolower($contentType), 'multipart/report')) {
            return null;
        }

        if (1 !== preg_match('#boundary="?([^";\s]+)"?#i', $contentType, $matches)) {
            return null;
        }

        $failures = [];

        foreach (explode('--'.$matches[1], $body) as $part) {
            foreach (self::failuresIn($part) as $failure) {
                $failures[] = $failure;
            }
        }

        return new self($failures);
    }

    /** @return list<FailedRecipient> */
    private static function failuresIn(string $part): array
    {
        [$headers, $body] = self::split(ltrim($part, "\n"));

        $contentType = self::field($headers, 'content-type');

        if (null === $contentType || ! str_contains(mb_strtolower($contentType), 'message/delivery-status')) {
            return [];
        }

        $failures = [];

        // The part opens with fields about the report itself (the reporting MTA,
        // the arrival date), then one blank-line separated group per recipient.
        foreach (preg_split('/\n{2,}/', trim($body)) ?: [] as $group) {
            $failure = self::failure($group);

            if ($failure instanceof FailedRecipient) {
                $failures[] = $failure;
            }
        }

        return $failures;
    }

    private static function failure(string $group): ?FailedRecipient
    {
        $recipient = self::field($group, 'final-recipient');
        $status = self::field($group, 'status');

        if (null === $recipient || null === $status) {
            return null;
        }

        if ('failed' !== mb_strtolower((string) self::field($group, 'action'))) {
            return null;
        }

        // Anything that is not an explicit permanent failure is left alone:
        // taking an address off the list is not undone by the next delivery.
        if (! str_starts_with($status, '5.')) {
            return null;
        }

        $email = self::address($recipient);

        return '' === $email ? null : new FailedRecipient($email, $status, self::field($group, 'diagnostic-code') ?? '');
    }

    /**
     * `Final-Recipient: rfc822; someone@example.tld`, address type included per
     * the RFC, sometimes with the address in angle brackets.
     */
    private static function address(string $finalRecipient): string
    {
        $parts = explode(';', $finalRecipient, 2);

        return mb_strtolower(trim(trim($parts[1] ?? $parts[0]), '<> '));
    }

    /**
     * @return array{0: string, 1: string} headers and body, either possibly empty
     *
     * Byte offsets on purpose: a returned message carries whatever encoding its
     * attachments were in, and multibyte-aware cutting would mangle a body that
     * is not valid UTF-8
     */
    private static function split(string $message): array
    {
        $blankLine = strpos($message, "\n\n");

        return false === $blankLine
            ? [$message, '']
            : [substr($message, 0, $blankLine), substr($message, $blankLine + 2)];
    }

    /** Reads one field out of a header block, folded continuation lines included. */
    private static function field(string $headers, string $name): ?string
    {
        $unfolded = preg_replace('/\n[ \t]+/', ' ', $headers) ?? $headers;

        foreach (explode("\n", $unfolded) as $line) {
            [$key, $value] = array_pad(explode(':', $line, 2), 2, '');

            if (mb_strtolower(trim($key)) === $name) {
                return trim($value);
            }
        }

        return null;
    }
}
