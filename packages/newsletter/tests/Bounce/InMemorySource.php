<?php

namespace Pushword\Newsletter\Tests\Bounce;

use Pushword\Newsletter\Bounce\BounceSource;

/**
 * A mailbox held in an array, which is what makes the reader testable without a
 * filesystem and without an IMAP server.
 *
 * It is the shape {@see \Pushword\Newsletter\Bounce\ImapSource} has — keys that
 * are not paths, a flag that can refuse — so what is asserted through it is what
 * the remote source does, minus the connection.
 */
final class InMemorySource implements BounceSource
{
    /** @var list<int|string> */
    public array $read = [];

    /**
     * @param array<int|string, string> $messages
     * @param list<int|string>          $unflaggable keys the server refuses to flag
     */
    public function __construct(
        private readonly array $messages,
        private readonly array $unflaggable = [],
    ) {
    }

    public function messages(): iterable
    {
        yield from $this->messages;
    }

    public function markRead(int|string $key): bool
    {
        if (\in_array($key, $this->unflaggable, true)) {
            return false;
        }

        $this->read[] = $key;

        return true;
    }
}
