<?php

namespace Pushword\Newsletter\Tests\Bounce;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Bounce\ImapSource;
use Pushword\Newsletter\Bounce\MaildirSource;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Service\BounceCollector;
use Pushword\Newsletter\Service\BounceSignature;
use Pushword\Newsletter\Service\ContactManager;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use RuntimeException;

/**
 * What the reader does with a mailbox that is not a directory. The rules are the
 * maildir's, unchanged: the source only decides where the messages come from.
 */
#[Group('integration')]
final class RemoteMailboxTest extends AbstractNewsletterTestCase
{
    public function testTheSameRulesApplyWhateverTheMailboxIs(): void
    {
        $audience = $this->createAudience();
        $dead = $this->createContact($audience, 'dead@example.tld');
        $alive = $this->createContact($audience, 'busy@example.tld');

        $source = new InMemorySource([
            101 => $this->bounceFor('dead@example.tld', '5.1.1'),
            102 => $this->bounceFor('busy@example.tld', '4.2.2'),
            103 => "From: someone@example.tld\r\nSubject: Not a report\r\n\r\nHello.",
        ]);

        $report = $this->collector()->collect($source);

        self::assertSame(3, $report['scanned']);
        self::assertSame(1, $report['marked'], 'one permanent failure acted on');
        self::assertSame(1, $report['soft'], 'one temporary failure counted, not acted on');
        self::assertSame(1, $report['foreign'], 'one message that was not a delivery report');

        $this->entityManager->refresh($dead);
        $this->entityManager->refresh($alive);
        self::assertSame(ContactStatus::Bounced, $dead->status);
        self::assertSame(ContactStatus::Subscribed, $alive->status);

        // Everything read is flagged, the two that were not bounces included, or
        // every run would read them again for ever.
        self::assertSame([101, 102, 103], $source->read);
    }

    /**
     * A flag the server refuses is counted and never fatal: the address is still
     * taken off the list, and re-reading the message next run costs nothing.
     */
    public function testAFlagThatFailsStillDropsTheAddress(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'dead@example.tld');

        $source = new InMemorySource(
            [101 => $this->bounceFor('dead@example.tld', '5.1.1')],
            unflaggable: [101],
        );

        $report = $this->collector()->collect($source);

        self::assertSame(1, $report['marked']);
        self::assertSame(1, $report['unfiled']);

        $this->entityManager->refresh($contact);
        self::assertSame(ContactStatus::Bounced, $contact->status);
    }

    public function testADryRunActsOnNothingAndFlagsNothing(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'dead@example.tld');

        $source = new InMemorySource([101 => $this->bounceFor('dead@example.tld', '5.1.1')]);

        $report = $this->collector()->collect($source, dryRun: true);

        self::assertSame(1, $report['marked'], 'a dry run still says what it would drop');

        $this->entityManager->refresh($contact);
        self::assertSame(ContactStatus::Subscribed, $contact->status);
    }

    public function testADsnIsReadIntoAConnection(): void
    {
        ['config' => $config, 'folder' => $folder] = ImapSource::parse('imaps://np%40altimood.com:p%40ss%2Fword@node212-eu.n0c.com:993/Bounces');

        self::assertSame('node212-eu.n0c.com', $config['host']);
        self::assertSame(993, $config['port']);
        self::assertSame('ssl', $config['encryption']);
        // A generated password holds `@` and `/` often enough that not decoding
        // them would authenticate as somebody else, or not at all.
        self::assertSame('np@altimood.com', $config['username']);
        self::assertSame('p@ss/word', $config['password']);
        self::assertSame('Bounces', $folder);
    }

    public function testADsnWithoutAFolderReadsTheInbox(): void
    {
        ['config' => $config, 'folder' => $folder] = ImapSource::parse('imap://user:pass@mail.example.tld');

        self::assertSame(143, $config['port'], 'the port follows the scheme when it is left out');
        self::assertSame('starttls', $config['encryption']);
        self::assertSame('INBOX', $folder);
    }

    public function testSomethingThatIsNotAUrlSaysSo(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/imaps:\/\/user/');

        ImapSource::parse('/home/user/mail/bounce');
    }

    /**
     * The source holds the one message it has handed out, so that flagging is
     * bounded to what the caller is actually working on — holding a run's worth
     * would keep every fetched body alive with them. A key that is not that
     * message is refused without the mailbox being touched, which is what lets
     * this run without an IMAP server.
     */
    public function testOnlyTheMessageInHandCanBeFlagged(): void
    {
        $source = new ImapSource('imaps://user:pass@mail.example.tld/INBOX');

        self::assertFalse($source->markRead(1), 'nothing has been handed out yet');
    }

    /** A dry run reports the flag it deliberately did not set. */
    public function testADryRunFlagsNothingAndSaysItDid(): void
    {
        $source = new ImapSource('imaps://user:pass@mail.example.tld/INBOX', dryRun: true);

        self::assertTrue($source->markRead(1));
    }

    /**
     * They are two ways to read one mailbox, so configuring both is a mistake
     * worth naming — at run time, where the env placeholder behind the DSN has
     * actually been resolved.
     */
    public function testConfiguringBothMailboxesIsRefused(): void
    {
        $collector = $this->configured('/var/mail/bounce', 'imaps://user:pass@mail.example.tld');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not both/');

        $collector->source();
    }

    public function testConfiguringNeitherSaysWhatToSet(): void
    {
        $collector = $this->configured(null, null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bounce_maildir/');

        $collector->source();
    }

    /** An explicit --maildir answers the question, so it is not also an ambiguity. */
    public function testAnExplicitMaildirWinsOverAConfiguredDsn(): void
    {
        $collector = $this->configured(null, 'imaps://user:pass@mail.example.tld');
        $maildir = sys_get_temp_dir().'/pw-bounces-'.bin2hex(random_bytes(6));
        mkdir($maildir.'/new', 0o755, true);

        try {
            self::assertInstanceOf(MaildirSource::class, $collector->source($maildir));
        } finally {
            rmdir($maildir.'/new');
            rmdir($maildir);
        }
    }

    /** A DSN with nothing to read it with names the package to install. */
    public function testAnEmptyStringIsNotAConfiguredMailbox(): void
    {
        $collector = $this->configured('   ', 'imaps://user:pass@mail.example.tld');

        self::assertInstanceOf(ImapSource::class, $collector->source());
    }

    private function configured(?string $maildir, ?string $dsn): BounceCollector
    {
        return new BounceCollector(
            self::getContainer()->get(ContactRepository::class),
            self::getContainer()->get(ContactManager::class),
            self::getContainer()->get(BounceSignature::class),
            $maildir,
            $dsn,
        );
    }

    private function collector(): BounceCollector
    {
        return self::getContainer()->get(BounceCollector::class);
    }
}
