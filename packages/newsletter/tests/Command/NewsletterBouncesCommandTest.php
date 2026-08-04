<?php

namespace Pushword\Newsletter\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Enum\ContactStatus;
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
        $this->deliver('1785786812.M517167P2151652.host', $this->bounce('dead@example.tld', '5.1.1'));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        $tester->assertCommandIsSuccessful();

        $this->entityManager->refresh($contact);
        self::assertSame(ContactStatus::Bounced, $contact->status);
        self::assertNotNull($contact->bouncedAt);

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);
        self::assertSame(1, $decoded['marked']);
    }

    /** Read once: the message moves to cur/, where the next run does not look. */
    public function testAReadMessageIsFiled(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'dead@example.tld');
        $this->deliver('1785786812.M517167P2151652.host', $this->bounce('dead@example.tld', '5.1.1'));

        $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        self::assertSame([], glob($this->maildir.'/new/*') ?: []);
        self::assertCount(1, glob($this->maildir.'/cur/*') ?: []);

        $second = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);
        $decoded = json_decode(trim($second->getDisplay()), true);
        self::assertIsArray($decoded);
        self::assertSame(0, $decoded['scanned']);
    }

    public function testADryRunTouchesNeitherTheListNorTheMailbox(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'dead@example.tld');
        $this->deliver('1785786812.M517167P2151652.host', $this->bounce('dead@example.tld', '5.1.1'));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--dry-run' => true, '--format' => 'agent']);

        $this->entityManager->refresh($contact);
        self::assertSame(ContactStatus::Subscribed, $contact->status);
        self::assertCount(1, glob($this->maildir.'/new/*') ?: []);

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);
        self::assertSame(1, $decoded['marked'], 'a dry run still says what it would drop');
    }

    /** The mailbox also collects the bounces of everything else the app sends. */
    public function testABounceForSomeoneWhoIsOnNoListIsOnlyReported(): void
    {
        $this->deliver('1785786813.M1P1.host', $this->bounce('stranger@example.tld', '5.1.1'));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);
        self::assertSame(0, $decoded['marked']);
        self::assertSame(['stranger@example.tld'], $decoded['unknown']);
    }

    public function testATemporaryFailureLeavesTheContactAlone(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'dead@example.tld');
        $this->deliver('1785786812.M517167P2151652.host', $this->bounce('dead@example.tld', '4.2.2'));

        $tester = $this->runBounces(['--maildir' => $this->maildir, '--format' => 'agent']);

        $this->entityManager->refresh($contact);
        self::assertSame(ContactStatus::Subscribed, $contact->status);

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);
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
        $this->deliver('1785786812.M1P1.host', $this->bounce('dead@example.tld', '5.1.1'));

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
        $this->deliver('1785786812.M1P1.host', $this->bounce('dead@example.tld', '5.1.1'));

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

    private function bounce(string $email, string $status): string
    {
        return BounceFixture::bounce($email, $status);
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
