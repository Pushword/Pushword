<?php

namespace Pushword\Newsletter\Bounce;

/**
 * The mailbox as a directory, which is what it is on a shared host.
 *
 * A bounce is a file, delivered next to every other mailbox, and reading it
 * needs no extension and no credentials. Maildir holds unread mail in `new/`,
 * and a message moved to `cur/` with the seen flag is one that will not be read
 * again.
 */
final readonly class MaildirSource implements BounceSource
{
    /**
     * A bounce carries the refused message back with it, which can run to
     * megabytes. Everything read here sits in the report part, written before
     * that attachment, so the head of the file is enough and a mailbox full of
     * large returns cannot exhaust memory.
     */
    private const int READ_BYTES = 65536;

    public function __construct(
        private string $path,
        private bool $dryRun = false,
    ) {
    }

    public function messages(): iterable
    {
        $unread = $this->path.'/new';

        if (! is_dir($unread)) {
            return;
        }

        $files = glob($unread.'/*') ?: [];

        // Oldest first: a maildir name opens with the delivery timestamp, so the
        // order the server wrote them in is the order they are answered in.
        sort($files);

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            if (! is_readable($file)) {
                continue;
            }

            yield $file => (string) file_get_contents($file, false, null, 0, self::READ_BYTES);
        }
    }

    public function markRead(int|string $key): bool
    {
        if ($this->dryRun) {
            return true;
        }

        $read = $this->path.'/cur';

        if (! is_dir($read) && ! mkdir($read, 0o755, true) && ! is_dir($read)) {
            return false;
        }

        if (! is_writable($read) || ! is_writable(\dirname((string) $key))) {
            return false;
        }

        // Maildir keeps a message's flags after `:2,` in its name, `S` for seen.
        return rename((string) $key, $read.'/'.basename((string) $key).':2,S');
    }
}
