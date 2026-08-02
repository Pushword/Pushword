<?php

namespace Pushword\Newsletter\Tests\Service;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
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

        self::assertSame(ContactStatus::Subscribed, $reopened->status);
        self::assertNull($reopened->unsubscribedAt);
        self::assertSame('second-thoughts', $reopened->source);
    }

    /**
     * An import rarely says which language the person reads, and the entity must
     * not invent one: a hardcoded 'en' mails English on a French install.
     */
    public function testAContactWithoutALocaleTakesTheOneOfTheAudiencesHost(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);

        $contact = $this->manager()->subscribe($audience, 'silent@example.tld');

        $site = self::getContainer()->get(SiteRegistry::class)->get($audience->mainHost);
        self::assertSame($site->locale, $contact->locale);
        self::assertNotSame('', $contact->locale, 'an empty locale renders lang="" and mails in the sender\'s language');
    }

    /** Saying nothing about the language is not saying English. */
    public function testASubmissionSilentOnTheLocaleLeavesTheKnownOneAlone(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $this->manager()->subscribe($audience, 'francois@example.tld', locale: 'fr');

        $contact = $this->manager()->subscribe($audience, 'francois@example.tld');

        self::assertSame('fr', $contact->locale);
    }

    /** Provenance must point at the moment consent was given, not at the latest form post. */
    public function testProvenanceIsNotOverwrittenByARepeatSubmission(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $this->manager()->subscribe($audience, 'known@example.tld', source: 'first-page');

        $contact = $this->manager()->subscribe($audience, 'known@example.tld', source: 'other-page');

        self::assertSame('first-page', $contact->source);
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

        self::assertSame(1, $campaign->unsubCount);
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

        self::assertSame(ContactStatus::Bounced, $contact->status);
        self::assertSame(1, $campaign->bounceCount);
        self::assertSame(RecipientState::Bounced, $this->recipientState($campaign));
    }

    public function testLeavingStopsEveryActiveSequence(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->createContact($audience, 'leaving@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 60, 'subject' => 'Welcome']]);

        $runner = self::getContainer()->get(AutomationRunner::class);
        $runner->triggerOne($automation, new DateTimeImmutable());

        $this->manager()->unsubscribe($contact);

        $enrollment = $this->entityManager->getRepository(Enrollment::class)->findOneBy(['contact' => $contact]);
        self::assertInstanceOf(Enrollment::class, $enrollment);
        self::assertSame(EnrollmentStatus::Stopped, $enrollment->status);
    }

    /** An opt-out taken back is not one the campaign's rate should keep carrying. */
    public function testTakingAnOptOutBackGivesTheCampaignItsCountBack(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->createContact($audience, 'oops@example.tld');
        $campaign = $this->createCampaign($audience);

        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);
        $sender->drain($campaign, 10);

        $this->manager()->unsubscribe($contact);
        self::assertSame(1, $campaign->unsubCount);

        $this->manager()->resubscribe($contact);

        self::assertSame(ContactStatus::Subscribed, $contact->status);
        self::assertSame(0, $campaign->unsubCount);
    }

    /** The mail server refused the address; a click on a page says nothing about that. */
    public function testABouncedAddressIsNotRevivedByTakingAnOptOutBack(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->createContact($audience, 'dead@example.tld');

        $this->manager()->markBounced($contact);
        $this->manager()->unsubscribe($contact);

        $this->manager()->resubscribe($contact);

        self::assertSame(ContactStatus::Unsubscribed, $contact->status);
        self::assertNotNull($contact->bouncedAt);
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

        self::assertSame(1, $campaign->unsubCount);
    }

    private function recipientState(Campaign $campaign): RecipientState
    {
        $recipients = $this->entityManager->getRepository(CampaignRecipient::class)->findBy(['campaign' => $campaign]);
        self::assertCount(1, $recipients);

        return $recipients[0]->state;
    }
}
