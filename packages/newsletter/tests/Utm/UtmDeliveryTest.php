<?php

namespace Pushword\Newsletter\Tests\Utm;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Service\AutomationRunner;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;

/**
 * What actually reaches an inbox. The decorator is covered on its own; this pins
 * the wiring in between — that a real send names the mail after the right thing.
 */
#[Group('integration')]
final class UtmDeliveryTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    public function testASentCampaignCarriesItsDatedName(): void
    {
        $audience = $this->createAudience();
        $audience->utmSource = 'newsletter';
        $this->createContact($audience, 'reader@example.tld');

        $campaign = $this->createCampaign($audience, subject: 'Janvier');
        $campaign->bodyMarkdown = '[Read](/article)';

        $this->entityManager->flush();

        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);
        $sender->drain($campaign, 10);

        self::assertStringContainsString(
            'https://localhost.dev/article?utm_source=newsletter&amp;utm_medium=email&amp;utm_campaign='.date('ymd').'-janvier',
            $this->lastHtml(),
        );
    }

    public function testAnAutomationStepCarriesItsPosition(): void
    {
        $audience = $this->createAudience();
        $audience->utmSource = 'newsletter';
        $this->createContact($audience, 'reader@example.tld');

        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);
        $automation->getOrderedSteps()[0]->bodyMarkdown = '[Read](/article)';
        $this->entityManager->flush();

        $runner = self::getContainer()->get(AutomationRunner::class);
        $runner->triggerOne($automation, new DateTimeImmutable());
        $runner->advance(10);

        $html = $this->lastHtml();
        self::assertStringContainsString('utm_campaign=welcome', $html);
        self::assertStringContainsString('utm_content=step-1', $html);
    }

    /** The audience is the on-switch: without a source, links leave exactly as written. */
    public function testNothingIsTaggedWhenTheAudienceHasNoSource(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');

        $campaign = $this->createCampaign($audience);
        $campaign->bodyMarkdown = '[Read](/article)';

        $this->entityManager->flush();

        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);
        $sender->drain($campaign, 10);

        self::assertStringContainsString('href="https://localhost.dev/article"', $this->lastHtml());
        self::assertStringNotContainsString('utm_', $this->lastHtml());
    }

    private function lastHtml(): string
    {
        $messages = self::getMailerMessages();
        $email = end($messages);
        self::assertInstanceOf(Email::class, $email);

        return (string) $email->getHtmlBody();
    }
}
