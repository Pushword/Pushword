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
 *
 * Reading who failed is not the same as believing it: what a report names is
 * only acted on once {@see \Pushword\Newsletter\Service\BounceSignature} has
 * recognised one of the {@see self::$messageIds} it gave back. Parsing stops
 * here; that decision belongs to the collector.
 */
final readonly class DeliveryReport
{
    /**
     * @param list<FailedRecipient> $failures
     * @param list<string>          $messageIds ids of the message that failed, as the
     *                                          returned copy of it carries them, angle
     *                                          brackets stripped
     */
    private function __construct(public array $failures, public array $messageIds)
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
        $messageIds = [];

        foreach (explode('--'.$matches[1], $body) as $part) {
            foreach (self::failuresIn($part) as $failure) {
                $failures[] = $failure;
            }

            $messageId = self::originalMessageIdIn($part);

            if (null !== $messageId) {
                $messageIds[] = $messageId;
            }
        }

        return new self($failures, $messageIds);
    }

    /**
     * The `Message-ID` of the message that failed, out of the copy of it the
     * report returns: `message/rfc822` when the whole message comes back,
     * `text/rfc822-headers` when only its headers do.
     *
     * RFC 3464 makes that part a SHOULD rather than a MUST, so a report may
     * name a recipient and carry nothing saying which message it is about. Such
     * a report proves nothing and is counted rather than acted on.
     */
    private static function originalMessageIdIn(string $part): ?string
    {
        [$headers, $body] = self::split(ltrim($part, "\n"));

        $contentType = mb_strtolower(self::field($headers, 'content-type') ?? '');

        $returnsTheMessage = str_contains($contentType, 'message/rfc822')
            || str_contains($contentType, 'text/rfc822-headers');

        if (! $returnsTheMessage) {
            return null;
        }

        // Split once more, so that a `Message-ID:` written in the body of the
        // returned message cannot pass for the real header. Headers returned on
        // their own hold no blank line, and come back whole.
        $messageId = self::field(self::split($body)[0], 'message-id');

        return null === $messageId ? null : trim($messageId, '<> ');
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
