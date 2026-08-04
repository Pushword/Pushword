<?php

namespace Pushword\Newsletter\Tests\Command;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class NewsletterBouncesCommandTest extends AbstractNewsletterTestCase
{
    private string $maildir = '';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->maildir = sys_get_temp_dir().'/pw-bounces-'.bin2hex(random_bytes(6));
        mkdir($this->maildir.'/new', 0o755, true);
    }

    #[Override]
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

    private function deliver(string $name, string $content): void
    {
        file_put_contents($this->maildir.'/new/'.$name, $content);
    }

    private function bounce(string $email, string $status): string
    {
        return str_replace("\n", "\r\n", <<<MAIL
            From: MAILER-DAEMON@relay.example.tld (Mail Delivery System)
            Subject: Undelivered Mail Returned to Sender
            To: bounce@example.tld
            MIME-Version: 1.0
            Content-Type: multipart/report; report-type=delivery-status;
            \tboundary="BOUNDARY"

            --BOUNDARY
            Content-Type: text/plain; charset=us-ascii

            Your message could not be delivered.

            --BOUNDARY
            Content-Type: message/delivery-status

            Reporting-MTA: dns; relay.example.tld

            Final-Recipient: rfc822; {$email}
            Action: failed
            Status: {$status}
            Diagnostic-Code: smtp; 550 no such user

            --BOUNDARY--
            MAIL);
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
