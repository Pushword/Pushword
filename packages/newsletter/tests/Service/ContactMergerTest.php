<?php

namespace Pushword\Newsletter\Tests\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\AutomationDelivery;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\Enrollment;
use Pushword\Newsletter\Service\ContactMerger;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;

/**
 * Two rows, one person: what a merge keeps, what it refuses, and what it costs
 * in history (nothing).
 */
#[Group('integration')]
final class ContactMergerTest extends AbstractNewsletterTestCase
{
    private function merger(): ContactMerger
    {
        return self::getContainer()->get(ContactMerger::class);
    }

    public function testTheAddressedRowKeepsItsIdAndItsUnsubscribeLink(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $addressed = $this->createContact($audience, 'both@example.tld');
        $phoneOnly = $this->createPhoneContact($audience, '0612345678');

        $id = $addressed->id;
        $token = $addressed->token;
        $absorbedId = $phoneOnly->id;
        self::assertIsInt($absorbedId);

        $kept = $this->merger()->merge($addressed, $phoneOnly);

        self::assertSame($id, $kept->id);
        self::assertSame($token, $kept->token, 'the links already in a mailbox have to keep working');
        self::assertSame('0612345678', $kept->phone);
        self::assertNull($this->entityManager->getRepository(Contact::class)->find($absorbedId));
    }

    /**
     * The same join, asked for from the other side: somebody known by phone who
     * gives an address already on the list. The address still wins, so the row
     * the caller held is the one that disappears.
     */
    public function testTheAddressWinsWhicheverSideAsked(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $phoneOnly = $this->createPhoneContact($audience, '0612345678');
        $addressed = $this->createContact($audience, 'both@example.tld');

        $kept = $this->merger()->merge($phoneOnly, $addressed);

        self::assertSame($addressed->id, $kept->id);
        self::assertSame('0612345678', $kept->phone);
    }

    public function testWhatTheOtherRowKnewAndTheKeptOneDidNot(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $addressed = $this->createContact($audience, 'blank@example.tld', tags: ['news'], customProperties: ['city' => 'Paris']);
        $addressed->name = '';

        $phoneOnly = $this->createPhoneContact($audience, '0612345678');
        $phoneOnly->name = 'Jeanne';
        $phoneOnly->setTags(['events']);
        $phoneOnly->customProperties = ['city' => 'Lyon', 'shopId' => 12];

        $this->entityManager->flush();

        $kept = $this->merger()->merge($addressed, $phoneOnly);

        self::assertSame('Jeanne', $kept->name, 'a blank is filled');
        self::assertSame('Paris', $kept->getCustomProperty('city'), 'what the kept row already knew is not overruled');
        self::assertSame(12, $kept->getCustomProperty('shopId'));
        self::assertEqualsCanonicalizing(['news', 'events'], $kept->getTagList(), 'interests add up');
    }

    /** A merge is not a delete: everything either row was sent follows the person. */
    public function testTheHistoryFollowsThePerson(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $addressed = $this->createContact($audience, 'kept@example.tld');
        $phoneOnly = $this->createPhoneContact($audience, '0612345678');

        $campaign = $this->createCampaign($audience);
        $recipient = new CampaignRecipient($campaign, $phoneOnly);
        $this->entityManager->persist($recipient);

        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);
        $enrollment = new Enrollment($phoneOnly, $automation, new DateTimeImmutable());
        $this->entityManager->persist($enrollment);

        $delivery = AutomationDelivery::sent($enrollment, 'Welcome');
        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        $kept = $this->merger()->merge($addressed, $phoneOnly);
        $this->entityManager->refresh($recipient);
        $this->entityManager->refresh($enrollment);
        $this->entityManager->refresh($delivery);

        self::assertSame($kept->id, $recipient->contact->id);
        self::assertSame($kept->id, $enrollment->contact->id);
        self::assertSame($kept->id, $delivery->contact->id, 'a step that went out is what says the sequence reached them');
    }

    /**
     * Both rows in one campaign, and both enrolled in one run of an automation:
     * the kept row already has its line, and either unique key says the thing
     * happened once. The duplicate goes with the row it belonged to.
     */
    public function testALedgerRowTheKeptContactAlreadyHasIsNotDuplicated(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $addressed = $this->createContact($audience, 'kept@example.tld');
        $phoneOnly = $this->createPhoneContact($audience, '0612345678');

        $campaign = $this->createCampaign($audience);
        $this->entityManager->persist(new CampaignRecipient($campaign, $addressed));
        $this->entityManager->persist(new CampaignRecipient($campaign, $phoneOnly));

        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);
        $this->entityManager->persist(new Enrollment($addressed, $automation, new DateTimeImmutable()));
        $this->entityManager->persist(new Enrollment($phoneOnly, $automation, new DateTimeImmutable()));
        $this->entityManager->flush();

        $kept = $this->merger()->merge($addressed, $phoneOnly);

        self::assertCount(1, $this->entityManager->getRepository(CampaignRecipient::class)->findBy(['campaign' => $campaign]));
        self::assertCount(1, $this->entityManager->getRepository(Enrollment::class)->findBy(['automation' => $automation]));
        self::assertSame($addressed->id, $kept->id);
    }

    public function testTwoAddressesAreTwoPeople(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $one = $this->createContact($audience, 'one@example.tld');
        $other = $this->createContact($audience, 'other@example.tld');

        self::assertNull($this->merger()->keeper($one, $other));

        $this->expectException(InvalidArgumentException::class);
        $this->merger()->merge($one, $other);
    }

    public function testTwoNumbersAreTwoPeople(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $one = $this->createPhoneContact($audience, '0612345678');
        $other = $this->createPhoneContact($audience, '0698765432');

        self::assertNull($this->merger()->keeper($one, $other));
    }

    /** Merging would drop one of the two numbers, and nothing here knows which. */
    public function testAKeptRowAlreadyHoldingAnotherNumberRefuses(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $addressed = $this->createContact($audience, 'kept@example.tld');
        $addressed->phone = '0600000000';

        $this->entityManager->flush();
        $phoneOnly = $this->createPhoneContact($audience, '0612345678');

        self::assertNull($this->merger()->keeper($addressed, $phoneOnly));
    }

    public function testTwoListsAreTwoSubscriptions(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $elsewhere = $this->createAudience(requireDoubleOptIn: false);
        $addressed = $this->createContact($audience, 'kept@example.tld');
        $phoneOnly = $this->createPhoneContact($elsewhere, '0612345678');

        self::assertNull($this->merger()->keeper($addressed, $phoneOnly));
    }

    public function testAContactIsNotMergedWithItself(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->createContact($audience, 'alone@example.tld');

        self::assertNull($this->merger()->keeper($contact, $contact));
    }

    /**
     * The kept row's own consent stands: an address that asked to be left alone
     * is not put back on the list by a number that never did.
     */
    public function testTheKeptRowsConsentIsTheOneThatSurvives(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $addressed = $this->createContact($audience, 'gone@example.tld');
        $addressed->unsubscribe();

        $this->entityManager->flush();
        $phoneOnly = $this->createPhoneContact($audience, '0612345678');

        $kept = $this->merger()->merge($addressed, $phoneOnly);

        self::assertFalse($kept->isSubscribed());
        self::assertNotNull($kept->unsubscribedAt);
    }
}
