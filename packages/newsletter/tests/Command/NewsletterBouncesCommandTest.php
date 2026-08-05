<?php

namespace Pushword\Newsletter\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Service\BounceSignature;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

#[Group('integration')]
final class NewsletterBouncesCommandTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    private string $maildir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->maildir = sys_get_temp_dir().'/pw-bounces-'.bin2hex(random_bytes(6));
        mkdir($this->maildir.'/new', 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->maildir.'/{new,cur}/*', \GLOB_BRACE) ?: [] as $file) {
            unlink($file);
        }

        foreach ([$this->maildir.'/new', $this->maildir.'/cur', $this->maildir] as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }

        parent::tearDown();
    }

    public function testAPermanentFailureTakesTheAddressOffTheList(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'dead@example.tld');
        $this->deliver('1785786812.M517167P2151652.host', $this->bounceFor('dead@example.tld', '5.1.1'));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        $tester->assertCommandIsSuccessful();

        $this->entityManager->refresh($contact);
        self::assertSame(ContactStatus::Bounced, $contact->status);
        self::assertNotNull($contact->bouncedAt);

        $decoded = $this->decode($tester);
        self::assertSame(1, $decoded['marked']);
    }

    /** Read once: the message moves to cur/, where the next run does not look. */
    public function testAReadMessageIsFiled(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'dead@example.tld');
        $this->deliver('1785786812.M517167P2151652.host', $this->bounceFor('dead@example.tld', '5.1.1'));

        $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        self::assertSame([], glob($this->maildir.'/new/*') ?: []);
        self::assertCount(1, glob($this->maildir.'/cur/*') ?: []);

        $second = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);
        $decoded = $this->decode($second);
        self::assertSame(0, $decoded['scanned']);
    }

    public function testADryRunTouchesNeitherTheListNorTheMailbox(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'dead@example.tld');
        $this->deliver('1785786812.M517167P2151652.host', $this->bounceFor('dead@example.tld', '5.1.1'));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--dry-run' => true, '--format' => 'agent']);

        $this->entityManager->refresh($contact);
        self::assertSame(ContactStatus::Subscribed, $contact->status);
        self::assertCount(1, glob($this->maildir.'/new/*') ?: []);

        $decoded = $this->decode($tester);
        self::assertSame(1, $decoded['marked'], 'a dry run still says what it would drop');
    }

    /** The mailbox also collects the bounces of everything else the app sends. */
    public function testABounceForSomeoneWhoIsOnNoListIsOnlyReported(): void
    {
        $this->deliver('1785786813.M1P1.host', $this->bounceFor('stranger@example.tld', '5.1.1'));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        $decoded = $this->decode($tester);
        self::assertSame(0, $decoded['marked']);
        self::assertSame(['stranger@example.tld'], $decoded['unknown']);
    }

    /**
     * The bounce mailbox is a published address that accepts mail from any
     * server on the internet. A hand-written `multipart/report` naming somebody
     * is not evidence of anything, and acting on one would let anybody take
     * anybody off the list — permanently, since a bounce is never taken back.
     */
    public function testAReportNamingNoMessageWeSentMarksNobody(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld');
        $this->deliver('1785786814.M1P1.host', BounceFixture::bounce('reader@example.tld', '5.1.1'));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        $this->entityManager->refresh($contact);
        self::assertSame(ContactStatus::Subscribed, $contact->status);
        self::assertNull($contact->bouncedAt);

        $decoded = $this->decode($tester);
        self::assertSame(0, $decoded['marked']);
        self::assertSame(1, $decoded['unverified']);
        self::assertSame([], $decoded['unknown'], 'an unproven report names nobody');
    }

    /**
     * A reader holds a real, correctly signed mail of ours — their own. Reusing
     * its headers under somebody else's `Final-Recipient` is the cheap way to
     * turn one genuine bounce into a weapon, and the signature is bound to the
     * address precisely so that it does not work.
     */
    public function testAReportReturningSomebodyElsesMessageMarksNobody(): void
    {
        $audience = $this->createAudience();
        $victim = $this->createContact($audience, 'victim@example.tld');
        $this->createContact($audience, 'reader@example.tld');

        $signature = self::getContainer()->get(BounceSignature::class);

        $this->deliver('1785786815.M1P1.host', BounceFixture::bounce(
            'victim@example.tld',
            '5.1.1',
            $signature->messageId('reader@example.tld', 'news@example.tld'),
        ));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        $this->entityManager->refresh($victim);
        self::assertSame(ContactStatus::Subscribed, $victim->status);
        self::assertSame(1, $this->decode($tester)['unverified']);
    }

    /**
     * The attack a signature has to survive in its most plausible form: rather
     * than rewriting whose bounce it is, append a second recipient group to a
     * genuine one of your own. The returned copy is then validly signed, the
     * report is half true, and only the half it can prove may be acted on.
     */
    public function testAVictimAppendedToAGenuineBounceIsNotMarked(): void
    {
        $audience = $this->createAudience();
        $reader = $this->createContact($audience, 'reader@example.tld');
        $victim = $this->createContact($audience, 'victim@example.tld');

        $this->deliver('1785786816.M1P1.host', str_replace(
            "Diagnostic-Code: smtp; 550 no such user\r\n",
            "Diagnostic-Code: smtp; 550 no such user\r\n\r\nFinal-Recipient: rfc822; victim@example.tld\r\nAction: failed\r\nStatus: 5.1.1\r\n",
            $this->bounceFor('reader@example.tld', '5.1.1'),
        ));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        $this->entityManager->refresh($reader);
        $this->entityManager->refresh($victim);
        self::assertSame(ContactStatus::Bounced, $reader->status, 'the half the report proves');
        self::assertSame(ContactStatus::Subscribed, $victim->status, 'the half it does not');

        $decoded = $this->decode($tester);
        self::assertSame(2, $decoded['failures']);
        self::assertSame(1, $decoded['marked']);
        self::assertSame(1, $decoded['unverified']);
    }

    /** The count is no use to whoever reads the mailbox if only the JSON carries it. */
    public function testTheConsoleSaysWhatItRefusedToActOn(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $this->deliver('1785786817.M1P1.host', BounceFixture::bounce('reader@example.tld', '5.1.1'));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'text']);

        self::assertStringContainsString('named no message this site sent', $tester->getDisplay());
    }

    /** And neither is it any use if the recap that gets mailed leaves it out. */
    public function testTheRecapCarriesWhatItRefusedToActOn(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $this->deliver('1785786818.M1P1.host', BounceFixture::bounce('reader@example.tld', '5.1.1'));

        $this->runBounces([
            '--maildir' => $this->maildir,
            '--notify' => 'ops@example.tld',
            '--format' => 'agent',
        ]);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertStringContainsString('named no message this site sent', (string) $email->getTextBody());
    }

    public function testATemporaryFailureLeavesTheContactAlone(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'dead@example.tld');
        $this->deliver('1785786812.M517167P2151652.host', $this->bounceFor('dead@example.tld', '4.2.2'));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        $this->entityManager->refresh($contact);
        self::assertSame(ContactStatus::Subscribed, $contact->status);

        $decoded = $this->decode($tester);
        self::assertSame(1, $decoded['soft']);
    }

    public function testAMissingMaildirFails(): void
    {
        $tester = $this->runBounces(['--maildir' => $this->maildir.'-gone', '--format' => 'text']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    /**
     * The command runs four times an hour. A recap on every one of them trains
     * its reader to filter it, which costs the one message that mattered.
     */
    public function testTheRecapIsMailedOnlyWhenSomethingMoved(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'dead@example.tld');
        $this->deliver('1785786812.M1P1.host', $this->bounceFor('dead@example.tld', '5.1.1'));

        $tester = $this->runBounces([
            '--maildir' => $this->maildir,
            '--notify' => 'ops@example.tld',
            '--format' => 'agent',
        ]);

        self::assertEmailCount(1);
        self::assertTrue($this->decode($tester)['notified']);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('ops@example.tld', $email->getTo()[0]->getAddress());
        self::assertStringContainsString('1 address(es) dropped', (string) $email->getTextBody());
    }

    public function testAnEmptyRunMailsNothing(): void
    {
        $tester = $this->runBounces([
            '--maildir' => $this->maildir,
            '--notify' => 'ops@example.tld',
            '--format' => 'agent',
        ]);

        self::assertEmailCount(0);
        self::assertFalse($this->decode($tester)['notified']);
    }

    /** A mailbox read to decide whether to let it act must not mail anybody about it. */
    public function testADryRunMailsNothing(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'dead@example.tld');
        $this->deliver('1785786812.M1P1.host', $this->bounceFor('dead@example.tld', '5.1.1'));

        $tester = $this->runBounces([
            '--maildir' => $this->maildir,
            '--notify' => 'ops@example.tld',
            '--dry-run' => true,
            '--format' => 'agent',
        ]);

        self::assertEmailCount(0);
        self::assertFalse($this->decode($tester)['notified']);
    }

    /** A message that was not a bounce moved nothing, so it is not worth a mail either. */
    public function testAMailboxHoldingNoBounceMailsNothing(): void
    {
        $this->deliver('1785786812.M1P1.host', "From: someone@example.tld\r\nSubject: Hello\r\n\r\nNot a report.");

        $tester = $this->runBounces([
            '--maildir' => $this->maildir,
            '--notify' => 'ops@example.tld',
            '--format' => 'agent',
        ]);

        self::assertSame(1, $this->decode($tester)['scanned']);
        self::assertEmailCount(0);
        self::assertFalse($this->decode($tester)['notified']);
    }

    /** @return array<array-key, mixed> */
    private function decode(CommandTester $tester): array
    {
        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function deliver(string $name, string $content): void
    {
        file_put_contents($this->maildir.'/new/'.$name, $content);
    }

    /** @param array<string, mixed> $input */
    private function runBounces(array $input): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $application = new Application($kernel);
        $tester = new CommandTester($application->find('pw:newsletter:bounces'));
        $tester->execute($input);

        return $tester;
    }
}
