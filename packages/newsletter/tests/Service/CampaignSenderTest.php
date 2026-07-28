<?php

namespace Pushword\Newsletter\Tests\Service;

use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Enum\RecipientState;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;

#[Group('integration')]
final class CampaignSenderTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    private function sender(): CampaignSender
    {
        return self::getContainer()->get(CampaignSender::class);
    }

    public function testArmingFreezesTheSegmentIntoRecipients(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'trek@example.tld', ['AmTrek']);
        $this->createContact($audience, 'other@example.tld');
        $campaign = $this->createCampaign($audience, [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]);

        $count = $this->sender()->arm($campaign);

        self::assertSame(1, $count);
        self::assertSame(CampaignStatus::Sending, $campaign->getStatus());
        self::assertSame(1, $campaign->getRecipientCount());
        self::assertSame('trek@example.tld', $this->recipients($campaign)[0]->getContact()->getEmail());
    }

    public function testArmingIgnoresContactsWhoAreNotSubscribed(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'in@example.tld');
        $this->createContact($audience, 'pending@example.tld', subscribed: false);

        $campaign = $this->createCampaign($audience);

        self::assertSame(1, $this->sender()->arm($campaign));
    }

    public function testArmingTwiceDoesNotDuplicateRecipients(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'one@example.tld');
        $campaign = $this->createCampaign($audience);

        $this->sender()->arm($campaign);
        $campaign->revertToDraft();
        $this->sender()->arm($campaign);

        self::assertCount(1, $this->recipients($campaign));
        self::assertSame(1, $campaign->getRecipientCount());
    }

    public function testASentCampaignCannotBeArmedAgain(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'one@example.tld');
        $campaign = $this->createCampaign($audience);
        $this->sender()->arm($campaign);

        $this->expectException(LogicException::class);

        $this->sender()->arm($campaign);
    }

    public function testDrainingSendsAndClosesTheCampaign(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience, subject: 'Hello %name%');
        $this->sender()->arm($campaign);

        $sent = $this->sender()->drain($campaign, 10);

        self::assertSame(1, $sent);
        self::assertEmailCount(1);
        self::assertSame(RecipientState::Sent, $this->recipients($campaign)[0]->getState());
        self::assertSame(1, $campaign->getSentCount());
        self::assertSame(CampaignStatus::Sent, $campaign->getStatus());

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Hello Test', $email->getSubject());
        self::assertStringContainsString('List-Unsubscribe', $email->getHeaders()->toString());
        self::assertStringContainsString('One-Click', $email->getHeaders()->toString());
    }

    /** The first mail goes out alone; the cadence then governs how fast the rest follow. */
    public function testCadenceLimitsWhatOneRunMaySend(): void
    {
        $audience = $this->createAudience(rateSeconds: 30);
        $this->createContact($audience, 'a@example.tld');
        $this->createContact($audience, 'b@example.tld');
        $this->createContact($audience, 'c@example.tld');
        $campaign = $this->createCampaign($audience);
        $this->sender()->arm($campaign);

        self::assertSame(1, $this->sender()->drain($campaign, 10));
        self::assertSame(0, $this->sender()->drain($campaign, 10), 'the cadence has not elapsed yet');

        // Pretend the last mail left a minute ago: two more are then allowed.
        $this->rewindLastSend($campaign, 60);

        self::assertSame(2, $this->sender()->drain($campaign, 10));
        self::assertSame(CampaignStatus::Sent, $campaign->getStatus());
    }

    public function testTheBudgetCapsARun(): void
    {
        $audience = $this->createAudience(rateSeconds: 1);
        $this->createContact($audience, 'a@example.tld');
        $this->createContact($audience, 'b@example.tld');
        $campaign = $this->createCampaign($audience);
        $this->sender()->arm($campaign);

        self::assertSame(1, $this->sender()->drain($campaign, 1));
        self::assertSame(CampaignStatus::Sending, $campaign->getStatus());
    }

    /** Consent can change between arming and sending; that is not a delivery failure. */
    public function testAContactWhoLeftAfterArmingIsSkipped(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'leaving@example.tld');
        $campaign = $this->createCampaign($audience);
        $this->sender()->arm($campaign);

        $contact->unsubscribe();
        $this->entityManager->flush();

        self::assertSame(0, $this->sender()->drain($campaign, 10));
        self::assertEmailCount(0);
        self::assertSame(RecipientState::Skipped, $this->recipients($campaign)[0]->getState());
        self::assertSame(0, $campaign->getFailedCount());
        self::assertSame(CampaignStatus::Sent, $campaign->getStatus());
    }

    public function testATransportFailureIsRecordedOnTheRecipient(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $this->sender()->arm($campaign);

        // An unusable sender identity: the mail can never be built.
        $audience->setFromEmail('not a valid address');
        $this->entityManager->flush();

        self::assertSame(0, $this->sender()->drain($campaign, 10));

        $recipient = $this->recipients($campaign)[0];
        self::assertSame(RecipientState::Failed, $recipient->getState());
        self::assertNotNull($recipient->getError());
        self::assertSame(1, $campaign->getFailedCount());
    }

    /** @return list<CampaignRecipient> */
    private function recipients(Campaign $campaign): array
    {
        /** @var list<CampaignRecipient> $recipients */
        $recipients = $this->entityManager->getRepository(CampaignRecipient::class)
            ->findBy(['campaign' => $campaign], ['id' => 'ASC']);

        return $recipients;
    }

    private function rewindLastSend(Campaign $campaign, int $seconds): void
    {
        foreach ($this->recipients($campaign) as $recipient) {
            $this->entityManager->detach($recipient);
        }

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE newsletter_campaign_recipient SET sent_at = :sentAt WHERE campaign_id = :campaign AND sent_at IS NOT NULL',
            ['sentAt' => date('Y-m-d H:i:s', time() - $seconds), 'campaign' => $campaign->id],
        );
    }
}
