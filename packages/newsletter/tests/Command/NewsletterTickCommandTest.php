<?php

namespace Pushword\Newsletter\Tests\Command;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class NewsletterTickCommandTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    public function testAScheduledCampaignIsArmedAndDrained(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $campaign->schedule(new DateTimeImmutable('-1 minute'));

        $this->entityManager->flush();

        $report = $this->tick();

        self::assertSame(1, $report['armed']);
        self::assertSame(1, $report['campaignMails']);
        self::assertEmailCount(1);
        self::assertSame(CampaignStatus::Sent, $this->reload($campaign)->getStatus());
    }

    public function testAFutureCampaignIsLeftAlone(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $campaign->schedule(new DateTimeImmutable('+1 day'));

        $this->entityManager->flush();

        $report = $this->tick();

        self::assertSame(0, $report['armed']);
        self::assertEmailCount(0);
        self::assertSame(CampaignStatus::Scheduled, $this->reload($campaign)->getStatus());
    }

    public function testAnAutomationEnrollsAndSendsInOneRun(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'new@example.tld');
        $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);

        $report = $this->tick();

        self::assertSame(1, $report['enrolled']);
        self::assertSame(1, $report['automationMails']);
        self::assertEmailCount(1);
    }

    public function testTheBudgetBoundsWhatOneRunSends(): void
    {
        $audience = $this->createAudience(rateSeconds: 1);
        $this->createContact($audience, 'a@example.tld');
        $this->createContact($audience, 'b@example.tld');
        $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);

        $report = $this->tick(['--batch' => 1]);

        self::assertSame(2, $report['enrolled']);
        self::assertSame(1, $report['automationMails']);
        self::assertEmailCount(1);
    }

    public function testHumanOutput(): void
    {
        $tester = $this->runTick(['--format' => 'text']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('campaign(s) armed', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, int>
     */
    private function tick(array $input = []): array
    {
        $tester = $this->runTick($input + ['--format' => 'agent']);
        $tester->assertCommandIsSuccessful();

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);

        /** @var array<string, int> $decoded */
        return $decoded;
    }

    /** @param array<string, mixed> $input */
    private function runTick(array $input): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $application = new Application($kernel);
        $command = $application->find('pw:newsletter:tick');

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    private function reload(Campaign $campaign): Campaign
    {
        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Campaign::class)->find($campaign->id);
        self::assertInstanceOf(Campaign::class, $reloaded);

        return $reloaded;
    }
}
