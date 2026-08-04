<?php

namespace Pushword\Newsletter\Bounce;

/**
 * A mailbox of delivery failures, whatever it is made of.
 *
 * The two that exist read the same mailbox from two sides — a maildir on the
 * filesystem of a shared host, an IMAP session against a provider — and the
 * whole of what the reader above needs from either is: hand me the unread
 * messages, and let me say I have read one. Which is why the parsing, the
 * `5.x.x`-only rule and the multi-audience drop live above this and are written
 * once.
 */
interface BounceSource
{
    /**
     * The unread messages, oldest first, as raw RFC 822 text.
     *
     * Only the head of each is promised: everything a delivery report says sits
     * in the report part, written before the returned message, which can run to
     * megabytes. A source truncates what it can — a maildir reads 64 KB off
     * disk and stops — so a mailbox full of large returns cannot exhaust memory.
     *
     * @return iterable<int|string, string> a key this source can be asked to mark read, and the message
     */
    public function messages(): iterable;

    /**
     * Say a message has been read, so the next run does not read it again.
     *
     * @return bool false when it could not be marked, which is counted and
     *              never fatal: re-reading costs nothing, since marking an
     *              address that already bounced is a no-op
     */
    public function markRead(int|string $key): bool;
}
