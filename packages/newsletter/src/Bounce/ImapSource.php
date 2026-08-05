<?php

namespace Pushword\Newsletter\Bounce;

use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\Message;

/**
 * The same mailbox, when it only exists on a remote server.
 *
 * The filesystem premise holds for a site whose PHP and whose mail live on the
 * same shared host. It fails for the arrangement that is now as common: the app
 * on a VPS or in a container, the mail at a provider. There the envelope
 * sender's mailbox is reachable by IMAP and by nothing else, so without this the
 * command has nothing to read, dead addresses stay subscribed, and every
 * campaign retries them.
 *
 * `\Seen` is the equivalent of the maildir `cur/` move, and searching `UNSEEN`
 * is the equivalent of reading `new/`. Same property, same consequence when it
 * fails: the message is read again, which costs nothing.
 *
 * One caveat the maildir does not have: **the bounce mailbox must not be read by
 * anything else that marks messages seen**. That is already the premise — a
 * mailbox nobody reads by hand — but pointing a DSN at a real inbox is a
 * temptation a filesystem path never offered.
 */
final class ImapSource implements BounceSource
{
    /**
     * As many as one run answers. The mailbox is read every quarter of an hour,
     * so a backlog drains over a few runs rather than in one long session
     * holding a connection open.
     */
    private const int BATCH = 200;

    /**
     * How many are asked for at a time, which is what a run actually holds.
     *
     * `get()` is eager: it fetches the body of every message on the page it was
     * given before it returns any of them. Asking for {@see BATCH} in one go
     * therefore builds 200 parsed messages at once — and a bounce carries the
     * message it failed to deliver, which is not small. A run that exhausts
     * memory there has flagged nothing, so the next one finds the same `UNSEEN`
     * messages and goes the same way, and the mailbox never drains.
     */
    private const int CHUNK = 25;

    /**
     * What is parsed of each message. IMAP hands back what it was asked for in
     * one piece, so unlike the maildir this bounds the parse and not the
     * transfer — a returned message still crosses the wire whole.
     */
    private const int READ_BYTES = 65536;

    /**
     * The handle of the message being handed out, so that it can be flagged.
     *
     * One, not a run's worth: the caller flags a message before asking for the
     * next, and a handle keeps the body it was fetched with alive, so keeping
     * them all would undo the chunking above.
     */
    private ?Message $current = null;

    private ?int $currentKey = null;

    public function __construct(
        private readonly string $dsn,
        private readonly bool $dryRun = false,
    ) {
    }

    public function messages(): iterable
    {
        foreach ($this->unread() as $message) {
            $uid = $message->getUid();

            $this->current = $message;
            $this->currentKey = $uid;

            yield $uid => mb_strcut($this->raw($message), 0, self::READ_BYTES);
        }

        $this->current = null;
        $this->currentKey = null;
    }

    public function markRead(int|string $key): bool
    {
        if ($this->dryRun) {
            return true;
        }

        // Only what is in hand can be flagged, which is all the caller ever
        // asks for. Anything else is a key this source never handed out.
        if ($key !== $this->currentKey || ! $this->current instanceof Message) {
            return false;
        }

        try {
            return $this->current->setFlag('Seen');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The unread messages of the mailbox the DSN names, a chunk at a time.
     *
     * Fetched without marking anything seen — `FT_PEEK` — because being read is
     * what {@see markRead()} says once the message has actually been acted on.
     * A run that dies halfway leaves the rest unread rather than silently
     * consumed.
     *
     * Every chunk asks for the **first** page again rather than the next one:
     * what this run has flagged has left the `UNSEEN` set, so a second page
     * would step over as many messages as the first page removed from it. A
     * message the server refuses to flag stays in that set and comes back —
     * hence `$handed`, without which the run would circle on it for ever.
     *
     * @return iterable<Message>
     */
    private function unread(): iterable
    {
        $folder = $this->folder();

        /** @var array<int, true> $handed */
        $handed = [];

        while (\count($handed) < self::BATCH) {
            $chunk = $folder->query()
                ->whereUnseen()
                ->leaveUnread()
                ->limit(self::CHUNK)
                ->get();

            $fresh = false;

            foreach ($chunk as $message) {
                // `MessageCollection` carries no value type a static analyser
                // can read, its `@implements` naming a class it does not extend.
                if (! $message instanceof Message) {
                    continue;
                }

                $uid = $message->getUid();

                if (isset($handed[$uid])) {
                    continue;
                }

                $handed[$uid] = true;
                $fresh = true;

                yield $message;

                if (\count($handed) >= self::BATCH) {
                    return;
                }
            }

            // An empty mailbox, or one holding nothing but messages this run
            // has already been given and could not flag.
            if (! $fresh) {
                return;
            }
        }
    }

    private function folder(): Folder
    {
        ['config' => $config, 'folder' => $folderName] = self::parse($this->dsn);

        $client = new ClientManager()->make($config);
        $client->connect();

        // The folder holds the client, so the connection outlives this call.
        return $client->getFolder($folderName)
            ?? throw new RuntimeException(\sprintf('No folder "%s" in the bounce mailbox.', $folderName));
    }

    /**
     * Header and body, as the parser reads a file off a maildir. A message
     * whose header could not be parsed carries no `Content-Type` either, so it
     * reads as "not a delivery report" and is counted rather than acted on.
     */
    private function raw(Message $message): string
    {
        $header = $message->getHeader();

        return ($header instanceof Header ? $header->raw : '')."\r\n\r\n".$message->getRawBody();
    }

    /**
     * `imaps://user:pass@host:993/INBOX` — the folder optional, `INBOX` when
     * left out, and both halves of the credentials percent-decoded, since a
     * generated password holds `@` and `/` often enough.
     *
     * @return array{config: array<string, mixed>, folder: string}
     */
    public static function parse(string $dsn): array
    {
        $parts = parse_url(trim($dsn));

        if (false === $parts || ! isset($parts['host'])) {
            throw new RuntimeException('The bounce IMAP DSN is not a URL: expected imaps://user:password@host:993/INBOX.');
        }

        $scheme = mb_strtolower($parts['scheme'] ?? 'imaps');
        $encrypted = 'imaps' === $scheme;
        $folder = trim($parts['path'] ?? '', '/');

        return [
            'config' => [
                'host' => $parts['host'],
                'port' => $parts['port'] ?? ($encrypted ? 993 : 143),
                'protocol' => 'imap',
                'encryption' => $encrypted ? 'ssl' : 'starttls',
                'validate_cert' => true,
                'username' => rawurldecode($parts['user'] ?? ''),
                'password' => rawurldecode($parts['pass'] ?? ''),
            ],
            'folder' => '' !== $folder ? rawurldecode($folder) : 'INBOX',
        ];
    }
}
