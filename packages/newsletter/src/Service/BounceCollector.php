<?php

namespace Pushword\Newsletter\Service;

use InvalidArgumentException;
use Pushword\Newsletter\Bounce\BounceSource;
use Pushword\Newsletter\Bounce\DeliveryReport;
use Pushword\Newsletter\Bounce\ImapSource;
use Pushword\Newsletter\Bounce\MaildirSource;
use Pushword\Newsletter\Repository\ContactRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webklex\PHPIMAP\ClientManager;

/**
 * Takes off the list the addresses a mail server has refused for good, by
 * reading the mailbox those refusals are delivered to.
 *
 * What makes that possible is the envelope sender pointing at a mailbox nobody
 * reads by hand (`framework.mailer.envelope.sender`), so failures land somewhere
 * a machine owns instead of in the middle of someone's correspondence.
 *
 * On a shared host that mailbox is a directory and reading it needs no extension
 * and no credentials; with the app on a VPS or in a container it usually only
 * exists on the provider's server, reachable by IMAP and by nothing else. Which
 * of the two it is, is a {@see BounceSource}'s business — the parsing, the
 * `5.x.x`-only rule and the multi-audience drop below are the same either way.
 *
 * Left alone, a dead address stays subscribed and is retried by every campaign,
 * which is how a sending reputation is spent.
 *
 * That mailbox accepts mail from anywhere, so a report naming an address is
 * never reason enough to drop it: {@see BounceSignature} has to recognise the
 * message the report came back about first.
 */
final readonly class BounceCollector
{
    public function __construct(
        private ContactRepository $contactRepository,
        private ContactManager $contactManager,
        private BounceSignature $bounceSignature,
        #[Autowire(param: 'pw.newsletter.bounce_maildir')]
        private ?string $configuredMaildir,
        #[Autowire(param: 'pw.newsletter.bounce_imap_dsn')]
        private ?string $configuredImapDsn,
    ) {
    }

    /** The mailbox to read, an explicit one winning over the configured default. */
    public function maildir(?string $override = null): ?string
    {
        $maildir = $this->clean($override ?? $this->configuredMaildir);

        return null === $maildir ? null : rtrim($maildir, '/');
    }

    public function imapDsn(): ?string
    {
        return $this->clean($this->configuredImapDsn);
    }

    /**
     * Which mailbox this run reads, and what to say when the answer is neither
     * or both.
     *
     * The exclusion is decided here rather than in `Configuration`, where it
     * cannot be: `bounce_imap_dsn: '%env(NEWSLETTER_BOUNCE_IMAP_DSN)%'` is still
     * an unresolved placeholder when the container compiles, so a build-time
     * rule would read it as set whatever the environment holds — and would
     * refuse to build every site that keeps both keys with one of them empty.
     *
     * @throws InvalidArgumentException with what to do about it
     */
    public function source(?string $maildirOverride = null, bool $dryRun = false): BounceSource
    {
        $maildir = $this->maildir($maildirOverride);

        // An explicit --maildir is an answer to this very question, so it is not
        // also an ambiguity with whatever the config holds.
        $dsn = null === $maildirOverride ? $this->imapDsn() : null;

        if (null !== $maildir && null !== $dsn) {
            throw new InvalidArgumentException('Set `newsletter.bounce_maildir` or `newsletter.bounce_imap_dsn`, not both: they are two ways to read one mailbox.');
        }

        if (null !== $dsn) {
            if (! class_exists(ClientManager::class)) {
                throw new InvalidArgumentException('Reading bounces over IMAP needs `composer require webklex/php-imap`.');
            }

            return new ImapSource($dsn, $dryRun);
        }

        if (null === $maildir) {
            throw new InvalidArgumentException('Set `newsletter.bounce_maildir` (the mailbox `framework.mailer.envelope.sender` points at), or `newsletter.bounce_imap_dsn` when that mailbox only exists on a remote server, or pass --maildir.');
        }

        if (! is_dir($maildir)) {
            throw new InvalidArgumentException(\sprintf('No maildir at "%s".', $maildir));
        }

        return new MaildirSource($maildir, $dryRun);
    }

    /**
     * Read every unread message in the mailbox and act on the bounces among them.
     *
     * A dry run counts what it would do and leaves both the database and the
     * mailbox untouched, which is the only way to look at a bounce mailbox for
     * the first time without committing to what a parser thinks it found.
     *
     * @return array{scanned: int, failures: int, marked: int, soft: int, foreign: int, unverified: int, unfiled: int, unknown: list<string>}
     */
    public function collect(BounceSource $source, bool $dryRun = false): array
    {
        $report = ['scanned' => 0, 'failures' => 0, 'marked' => 0, 'soft' => 0, 'foreign' => 0, 'unverified' => 0, 'unfiled' => 0, 'unknown' => []];

        foreach ($source->messages() as $key => $raw) {
            ++$report['scanned'];

            $deliveryReport = DeliveryReport::fromRaw($raw);

            if (! $deliveryReport instanceof DeliveryReport) {
                ++$report['foreign'];
            } elseif ([] === $deliveryReport->failures) {
                ++$report['soft'];
            } else {
                foreach ($deliveryReport->failures as $failure) {
                    ++$report['failures'];

                    // Unproven, so nothing is looked up and nothing is said
                    // about the address: it is not this report's to name.
                    if (! $this->bounceSignature->proves($failure->email, $deliveryReport->messageIds)) {
                        ++$report['unverified'];

                        continue;
                    }

                    $report['marked'] += $this->markBounced($failure->email, $dryRun, $report['unknown']);
                }
            }

            // Marking read is what keeps a run from repeating itself. What was
            // not a bounce is marked too, or every run would re-read it for
            // ever. A message that cannot be marked is only counted: re-reading
            // it later costs nothing, since marking an address that already
            // bounced is a no-op.
            if (! $source->markRead($key)) {
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

    private function clean(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }
}
