<?php

namespace Pushword\Newsletter\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class NewsletterSendCommandTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    /** Arming returns at once whatever the audience size; the tick delivers. */
    public function testArmingQueuesWithoutSending(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);

        $tester = $this->runSend(['campaign' => $campaign->id, '--format' => 'agent']);

        $tester->assertCommandIsSuccessful();

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);
        self::assertSame(1, $decoded['recipients']);
        self::assertEmailCount(0);
        self::assertSame(CampaignStatus::Sending, $campaign->getStatus());
    }

    public function testHumanOutput(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);

        $tester = $this->runSend(['campaign' => $campaign->id, '--format' => 'text']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('recipient(s) queued', $tester->getDisplay());
    }

    public function testAnUnknownCampaignFails(): void
    {
        $tester = $this->runSend(['campaign' => 999999, '--format' => 'text']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testACampaignAlreadySentCannotBeArmedAgain(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $this->runSend(['campaign' => $campaign->id, '--format' => 'text']);

        $tester = $this->runSend(['campaign' => $campaign->id, '--format' => 'text']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('sending', $tester->getDisplay());
    }

    /** @param array<string, mixed> $input */
    private function runSend(array $input): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $application = new Application($kernel);
        $command = $application->find('pw:newsletter:send');

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }
}
