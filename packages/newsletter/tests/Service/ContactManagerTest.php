<?php

namespace Pushword\Newsletter\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Entity\Enrollment;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Enum\EnrollmentStatus;
use Pushword\Newsletter\Enum\RecipientState;
use Pushword\Newsletter\Service\AutomationRunner;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Service\ContactManager;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

#[Group('integration')]
final class ContactManagerTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    private function manager(): ContactManager
    {
        return self::getContainer()->get(ContactManager::class);
    }

    public function testOptingInAgainAfterLeavingReopensTheSubscription(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->createContact($audience, 'back@example.tld');
        $this->manager()->unsubscribe($contact);

        $reopened = $this->manager()->subscribe($audience, 'back@example.tld', source: 'second-thoughts');

        self::assertSame(ContactStatus::Subscribed, $reopened->getStatus());
        self::assertNull($reopened->getUnsubscribedAt());
        self::assertSame('second-thoughts', $reopened->getSource());
    }

    /** Provenance must point at the moment consent was given, not at the latest form post. */
    public function testProvenanceIsNotOverwrittenByARepeatSubmission(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $this->manager()->subscribe($audience, 'known@example.tld', source: 'first-page');

        $contact = $this->manager()->subscribe($audience, 'known@example.tld', source: 'other-page');

        self::assertSame('first-page', $contact->getSource());
    }

    public function testUnsubscribingIsCreditedToTheLastCampaignReceived(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);

        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);
        $sender->drain($campaign, 10);

        $this->manager()->unsubscribe($contact);

        self::assertSame(1, $campaign->getUnsubCount());
    }

    public function testABounceIsCreditedAndMarksTheRecipient(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->createContact($audience, 'dead@example.tld');
        $campaign = $this->createCampaign($audience);

        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);
        $sender->drain($campaign, 10);

        $this->manager()->markBounced($contact);

        self::assertSame(ContactStatus::Bounced, $contact->getStatus());
        self::assertSame(1, $campaign->getBounceCount());
        self::assertSame(RecipientState::Bounced, $this->recipientState($campaign));
    }

    public function testLeavingStopsEveryActiveSequence(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->createContact($audience, 'leaving@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 60, 'subject' => 'Welcome']]);

        $runner = self::getContainer()->get(AutomationRunner::class);
        $runner->enroll($automation);

        $this->manager()->unsubscribe($contact);

        $enrollment = $this->entityManager->getRepository(Enrollment::class)->findOneBy(['contact' => $contact]);
        self::assertInstanceOf(Enrollment::class, $enrollment);
        self::assertSame(EnrollmentStatus::Stopped, $enrollment->getStatus());
    }

    public function testUnsubscribingTwiceChangesNothing(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->createContact($audience, 'gone@example.tld');
        $campaign = $this->createCampaign($audience);

        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);
        $sender->drain($campaign, 10);

        $this->manager()->unsubscribe($contact);
        $this->manager()->unsubscribe($contact);

        self::assertSame(1, $campaign->getUnsubCount());
    }

    private function recipientState(Campaign $campaign): RecipientState
    {
        $recipients = $this->entityManager->getRepository(CampaignRecipient::class)->findBy(['campaign' => $campaign]);
        self::assertCount(1, $recipients);

        return $recipients[0]->getState();
    }
}
