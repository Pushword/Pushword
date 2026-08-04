<?php

namespace Pushword\Newsletter\Bounce;

use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
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
     * What is parsed of each message. IMAP hands back what it was asked for in
     * one piece, so unlike the maildir this bounds the parse and not the
     * transfer — a returned message still crosses the wire whole.
     */
    private const int READ_BYTES = 65536;

    /**
     * The handles of what has been handed out, so a key can be flagged.
     *
     * The generator yields one message at a time and the caller marks it read
     * before asking for the next, so what is held is a run's worth of handles
     * and never a copy of the mailbox.
     *
     * @var array<int|string, Message>
     */
    private array $fetched = [];

    public function __construct(
        private readonly string $dsn,
        private readonly bool $dryRun = false,
    ) {
    }

    public function messages(): iterable
    {
        foreach ($this->unread() as $message) {
            $uid = $message->getUid();
            $this->fetched[$uid] = $message;

            yield $uid => mb_strcut($this->raw($message), 0, self::READ_BYTES);
        }
    }

    public function markRead(int|string $key): bool
    {
        if ($this->dryRun) {
            return true;
        }

        $message = $this->fetched[$key] ?? null;

        if (! $message instanceof Message) {
            return false;
        }

        try {
            return $message->setFlag('Seen');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The unread messages of the mailbox the DSN names.
     *
     * Fetched without marking anything seen — `FT_PEEK` — because being read is
     * what {@see markRead()} says once the message has actually been acted on.
     * A run that dies halfway leaves the rest unread rather than silently
     * consumed.
     *
     * @return iterable<Message>
     */
    private function unread(): iterable
    {
        ['config' => $config, 'folder' => $folderName] = self::parse($this->dsn);

        $client = new ClientManager()->make($config);
        $client->connect();

        $folder = $client->getFolder($folderName)
            ?? throw new RuntimeException(\sprintf('No folder "%s" in the bounce mailbox.', $folderName));

        return $folder->query()
            ->whereUnseen()
            ->leaveUnread()
            ->limit(self::BATCH)
            ->get();
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
