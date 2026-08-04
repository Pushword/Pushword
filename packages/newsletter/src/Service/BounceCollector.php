<?php

namespace Pushword\Newsletter\Service;

use Pushword\Newsletter\Bounce\DeliveryReport;
use Pushword\Newsletter\Repository\ContactRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Takes off the list the addresses a mail server has refused for good, by
 * reading the mailbox those refusals are delivered to.
 *
 * There is no webhook to receive and no IMAP session to open: on a shared host
 * a bounce is a file, written next to every other mailbox, and reading it needs
 * no extension and no credentials. What makes that possible is the envelope
 * sender pointing at a mailbox nobody reads by hand
 * (`framework.mailer.envelope.sender`), so failures land somewhere a machine
 * owns instead of in the middle of someone's correspondence.
 *
 * Left alone, a dead address stays subscribed and is retried by every campaign,
 * which is how a sending reputation is spent.
 */
final readonly class BounceCollector
{
    /**
     * A bounce carries the refused message back with it, which can run to
     * megabytes. Everything read here sits in the report part, written before
     * that attachment, so the head of the file is enough and a mailbox full of
     * large returns cannot exhaust memory.
     */
    private const int READ_BYTES = 65536;

    public function __construct(
        private ContactRepository $contactRepository,
        private ContactManager $contactManager,
        #[Autowire(param: 'pw.newsletter.bounce_maildir')]
        private ?string $configuredMaildir,
    ) {
    }

    /** The mailbox to read, an explicit one winning over the configured default. */
    public function maildir(?string $override = null): ?string
    {
        $maildir = $override ?? $this->configuredMaildir;

        return null === $maildir || '' === trim($maildir) ? null : rtrim(trim($maildir), '/');
    }

    /**
     * Read every unread message in the mailbox and act on the bounces among them.
     *
     * A dry run counts what it would do and leaves both the database and the
     * mailbox untouched, which is the only way to look at a bounce mailbox for
     * the first time without committing to what a parser thinks it found.
     *
     * @return array{scanned: int, failures: int, marked: int, soft: int, foreign: int, unfiled: int, unknown: list<string>}
     */
    public function collect(string $maildir, bool $dryRun = false): array
    {
        $report = ['scanned' => 0, 'failures' => 0, 'marked' => 0, 'soft' => 0, 'foreign' => 0, 'unfiled' => 0, 'unknown' => []];

        $unread = $maildir.'/new';
        $read = $maildir.'/cur';

        if (! is_dir($unread)) {
            return $report;
        }

        $files = glob($unread.'/*') ?: [];

        // Oldest first: a maildir name opens with the delivery timestamp, so the
        // order the server wrote them in is the order they are answered in.
        sort($files);

        foreach ($files as $file) {
            if (! is_file($file) || ! is_readable($file)) {
                continue;
            }

            ++$report['scanned'];

            $deliveryReport = DeliveryReport::fromRaw((string) file_get_contents($file, false, null, 0, self::READ_BYTES));

            if (! $deliveryReport instanceof DeliveryReport) {
                ++$report['foreign'];
            } elseif ([] === $deliveryReport->failures) {
                ++$report['soft'];
            } else {
                foreach ($deliveryReport->failures as $failure) {
                    ++$report['failures'];
                    $report['marked'] += $this->markBounced($failure->email, $dryRun, $report['unknown']);
                }
            }

            if (! $this->file($file, $read, $dryRun)) {
                ++$report['unfiled'];
            }
        }

        $report['unknown'] = array_values(array_unique($report['unknown']));

        return $report;
    }

    /**
     * One address can hold a subscription on several audiences, and the server
     * refused the address, not one of the lists: every one of them goes.
     *
     * @param list<string> $unknown addresses nobody on the list answers to, appended to
     */
    private function markBounced(string $email, bool $dryRun, array &$unknown): int
    {
        $contacts = $this->contactRepository->findAllByEmail($email);

        if ([] === $contacts) {
            // A bounce for something else this mailbox receives, a contact form
            // notification for instance. Counted, never acted on.
            $unknown[] = $email;

            return 0;
        }

        $marked = 0;

        foreach ($contacts as $contact) {
            if (null !== $contact->bouncedAt) {
                continue;
            }

            ++$marked;

            if (! $dryRun) {
                $this->contactManager->markBounced($contact);
            }
        }

        return $marked;
    }

    /**
     * Filing a message is what keeps a run from repeating itself: maildir holds
     * unread mail in `new/`, and a message moved to `cur/` with the seen flag is
     * one that will not be read again. What was not a bounce is filed too, or
     * every run would re-read it for ever.
     *
     * A message that cannot be filed is only counted. Re-reading it later costs
     * nothing: marking an address that already bounced is a no-op.
     */
    private function file(string $path, string $read, bool $dryRun): bool
    {
        if ($dryRun) {
            return true;
        }

        if (! is_dir($read) && ! mkdir($read, 0o755, true) && ! is_dir($read)) {
            return false;
        }

        if (! is_writable($read) || ! is_writable(\dirname($path))) {
            return false;
        }

        // Maildir keeps a message's flags after `:2,` in its name, `S` for seen.
        return rename($path, $read.'/'.basename($path).':2,S');
    }
}
