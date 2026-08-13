<?php

namespace Pushword\Newsletter\Tests\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\ContactEvent;
use Pushword\Newsletter\Enum\ContactTransition;
use Pushword\Newsletter\Repository\ContactEventRepository;
use Pushword\Newsletter\Service\ContactManager;
use Pushword\Newsletter\Service\ContactMerger;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;

/**
 * What is left to produce when somebody says "I never asked for this".
 *
 * The contact's own dates answer where things stand; every test here is about
 * the part they cannot answer — how they got there.
 */
#[Group('integration')]
final class ConsentLedgerTest extends AbstractNewsletterTestCase
{
    /**
     * The case the dates on the contact lose: a subscription left, re-opened and
     * left again keeps one `unsubscribedAt`, and the first departure — the one a
     * complaint is usually about — is gone from it.
     */
    public function testACycleOfLeavingAndComingBackKeepsEveryStep(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->manager()->subscribe($audience, 'cycle@example.tld', source: 'landing');

        $this->manager()->unsubscribe($contact, 'link');
        $this->manager()->subscribe($audience, 'cycle@example.tld', source: 'second-landing');
        $this->manager()->unsubscribe($contact, 'link');

        self::assertSame(
            [ContactTransition::OptIn, ContactTransition::Unsubscribe, ContactTransition::OptIn, ContactTransition::Unsubscribe],
            $this->transitionsOf($contact),
        );

        $ledger = $this->ledger()->findFor($contact);
        self::assertSame('landing', $ledger[0]->source, 'the first opt-in has to survive the second');
        self::assertSame('second-landing', $ledger[2]->source);
    }

    /** The double opt-in, and the click that answers it, are two acts. */
    public function testTheConfirmationIsRecordedNextToTheOptInItAnswers(): void
    {
        $audience = $this->createAudience();
        $contact = $this->manager()->subscribe($audience, 'doi@example.tld', source: 'form', optinHost: 'example.tld', optinIp: '203.0.113.1');

        $this->manager()->confirm($contact, source: 'link', host: 'example.tld', ip: '203.0.113.9');

        $ledger = $this->ledger()->findFor($contact);
        self::assertSame([ContactTransition::OptIn, ContactTransition::Confirm], $this->transitionsOf($contact));
        self::assertSame('203.0.113.1', $ledger[0]->ip);
        self::assertSame('203.0.113.9', $ledger[1]->ip, 'the click is its own evidence, from wherever it was made');
    }

    /**
     * An opt-out is never something a site is asked to prove, so nothing here
     * collects a place or an address to prove it with. The factory takes none:
     * the rule is structural, not a habit callers have to keep.
     */
    public function testAWithdrawalRecordsNoHostAndNoIp(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->manager()->subscribe($audience, 'gone@example.tld', optinHost: 'example.tld', optinIp: '203.0.113.1');

        $this->manager()->unsubscribe($contact, 'link');

        $withdrawal = $this->ledger()->findFor($contact)[1];
        self::assertSame(ContactTransition::Unsubscribe, $withdrawal->transition);
        self::assertSame('link', $withdrawal->source);
        self::assertNull($withdrawal->host);
        self::assertNull($withdrawal->ip);
    }

    /** Nothing about the consent moved, so there is nothing to record. */
    public function testARepeatSubmissionBySomebodyAlreadySubscribedAppendsNothing(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->manager()->subscribe($audience, 'twice@example.tld', source: 'first-page');

        $this->manager()->subscribe($audience, 'twice@example.tld', name: 'Robin', source: 'other-page');

        self::assertSame([ContactTransition::OptIn], $this->transitionsOf($contact));
    }

    /** Taking an opt-out back is consent given again, and it is dated as such. */
    public function testUndoingAnOptOutIsRecordedAsItsOwnAct(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->manager()->subscribe($audience, 'undo@example.tld');
        $this->manager()->unsubscribe($contact, 'link');

        $this->manager()->resubscribe($contact, 'link', 'example.tld', '203.0.113.4');

        $undo = $this->ledger()->findFor($contact)[2];
        self::assertSame(ContactTransition::Resubscribe, $undo->transition);
        self::assertSame('203.0.113.4', $undo->ip);
        self::assertNull($contact->unsubscribedAt, 'the state is back to subscribed, and the ledger still holds the departure');
    }

    /**
     * A merge costs no history — including the half that matters most. Both rows
     * were the same person, and dropping the absorbed one's ledger would destroy
     * the evidence for every list it ever consented on.
     */
    public function testAMergeMovesTheAbsorbedRowsHistoryToTheSurvivor(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $addressed = $this->manager()->subscribe($audience, 'both@example.tld', source: 'form');
        $phoneOnly = $this->manager()->subscribe($audience, source: 'admin:robin', phone: '0612345678');

        $kept = self::getContainer()->get(ContactMerger::class)->merge($addressed, $phoneOnly);

        $sources = array_map(static fn (ContactEvent $contactEvent): ?string => $contactEvent->source, $this->ledger()->findFor($kept));
        self::assertSame(['form', 'admin:robin'], $sources);
    }

    /**
     * A partitioned audience, on both planes at once: the state is the consent
     * that was already given, the ledger line is today's act of making a row.
     * Getting either one wrong destroys evidence — an antedated line claims an
     * act nobody performed, a renewed `confirmedAt` erases the real agreement.
     */
    public function testASplitCarriesTheConsentAndDatesOnlyTheRowItMakes(): void
    {
        $origin = $this->createAudience(requireDoubleOptIn: false);
        $target = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->manager()->subscribe($origin, 'split@example.tld', name: 'Robin', locale: 'fr', source: 'homepage', optinHost: 'example.tld', optinIp: '203.0.113.1');
        $contact->clickTrackingConsentAt = new DateTimeImmutable('-1 month');

        $this->entityManager->flush();

        $carried = $this->manager()->splitFrom($contact, $target);

        self::assertNotSame($contact->id, $carried->id);
        self::assertSame($contact->confirmedAt, $carried->confirmedAt, 'the partition divides a consent, it does not renew it');
        self::assertSame('homepage', $carried->source);
        self::assertSame('203.0.113.1', $carried->optinIp);
        self::assertEquals($contact->clickTrackingConsentAt, $carried->clickTrackingConsentAt);
        self::assertSame('Robin', $carried->name);
        self::assertSame('fr', $carried->locale);
        self::assertNotSame($contact->token, $carried->token, 'each row governs its own list, so it owns its own links');

        $ledger = $this->ledger()->findFor($carried);
        self::assertCount(1, $ledger);
        self::assertSame(ContactTransition::Split, $ledger[0]->transition);
        self::assertSame('split:'.$origin->slug, $ledger[0]->source);
        self::assertNull($ledger[0]->ip, 'a split accords no consent, so it records no place');
        self::assertGreaterThan($contact->confirmedAt, $ledger[0]->occurredAt);
    }

    /**
     * A switch-over is re-run — interrupted, resumed, aimed at a subset — and a
     * second pass must leave the first one's work alone. Above all it must not
     * raise somebody who left the second list: an existing row closes the door
     * whatever its status.
     */
    public function testASecondSplitReturnsTheExistingRowWithoutRaisingIt(): void
    {
        $origin = $this->createAudience(requireDoubleOptIn: false);
        $target = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->manager()->subscribe($origin, 'again@example.tld');

        $carried = $this->manager()->splitFrom($contact, $target);
        $this->manager()->unsubscribe($carried, 'link');

        $second = $this->manager()->splitFrom($contact, $target);

        self::assertSame($carried->id, $second->id);
        self::assertFalse($second->isSubscribed(), 'somebody who left the second list has left it');
        self::assertSame(
            [ContactTransition::Split, ContactTransition::Unsubscribe],
            $this->transitionsOf($second),
            'a repeat pass writes nothing',
        );
    }

    /** A tag says what somebody is on *this* list for; the origin's vocabulary is its own. */
    public function testASplitCarriesOnlyTheInterestsTheSecondListDeclares(): void
    {
        $origin = $this->createAudience(requireDoubleOptIn: false, interests: ['wine', 'cheese']);
        $target = $this->createAudience(requireDoubleOptIn: false, interests: ['cheese']);
        $contact = $this->manager()->subscribe($origin, 'tastes@example.tld', interests: ['wine', 'cheese']);

        $carried = $this->manager()->splitFrom($contact, $target);

        self::assertSame(['cheese'], $carried->getTagList());
    }

    /**
     * Nothing to carry. A pending contact reaches the second list when they
     * answer their confirmation, not by being copied ahead of it — that would
     * make a row for a consent that does not exist yet, and it could never
     * become mailable, having no confirmation of its own to send.
     */
    public function testOnlyALiveSubscriptionCanBeCarried(): void
    {
        $origin = $this->createAudience();
        $target = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->manager()->subscribe($origin, 'waiting@example.tld');

        $this->expectException(InvalidArgumentException::class);
        $this->manager()->splitFrom($contact, $target);
    }

    /** An erasure that left the ledger standing would keep the data it was asked to remove. */
    public function testDeletingAContactTakesItsLedgerWithIt(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $contact = $this->manager()->subscribe($audience, 'erased@example.tld');
        $id = $contact->id;

        $this->entityManager->remove($contact);
        $this->entityManager->flush();

        self::assertSame([], $this->entityManager->getRepository(ContactEvent::class)->findBy(['contact' => $id]));
    }

    /** @return list<ContactTransition> */
    private function transitionsOf(Contact $contact): array
    {
        return array_map(
            static fn (ContactEvent $contactEvent): ContactTransition => $contactEvent->transition,
            $this->ledger()->findFor($contact),
        );
    }

    private function ledger(): ContactEventRepository
    {
        return self::getContainer()->get(ContactEventRepository::class);
    }

    private function manager(): ContactManager
    {
        return self::getContainer()->get(ContactManager::class);
    }
}
