<?php

namespace Pushword\Newsletter\Tests\Segment;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;

#[Group('integration')]
final class SegmentResolverTest extends AbstractNewsletterTestCase
{
    private function resolver(): SegmentResolver
    {
        return self::getContainer()->get(SegmentResolver::class);
    }

    public function testAnEmptySegmentIsTheWholeSubscribedAudience(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'a@example.tld');
        $this->createContact($audience, 'b@example.tld');

        self::assertSame(2, $this->resolver()->count($audience, []));
    }

    public function testOnlySubscribedContactsAreEverReachable(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'subscribed@example.tld');
        $this->createContact($audience, 'pending@example.tld', subscribed: false);

        $unsubscribed = $this->createContact($audience, 'gone@example.tld');
        $unsubscribed->unsubscribe();

        $bounced = $this->createContact($audience, 'dead@example.tld');
        $bounced->markBounced();

        $this->entityManager->flush();

        $contacts = $this->resolver()->contacts($audience, []);

        self::assertCount(1, $contacts);
        self::assertSame('subscribed@example.tld', $contacts[0]->email);
    }

    public function testAudienceIsAlwaysScoped(): void
    {
        $audience = $this->createAudience();
        $other = $this->createAudience();
        $this->createContact($audience, 'mine@example.tld');
        $this->createContact($other, 'theirs@example.tld');

        self::assertSame(1, $this->resolver()->count($audience, []));
    }

    public function testTagHasAndHasNot(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'trek@example.tld', ['AmTrek', 'AmClient']);
        $this->createContact($audience, 'other@example.tld', ['AmBivouac']);

        $has = $this->resolver()->contacts($audience, [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]);
        self::assertCount(1, $has);
        self::assertSame('trek@example.tld', $has[0]->email);

        $hasNot = $this->resolver()->contacts($audience, [['field' => 'tag', 'op' => 'hasNot', 'value' => 'AmTrek']]);
        self::assertCount(1, $hasNot);
        self::assertSame('other@example.tld', $hasNot[0]->email);
    }

    /** A shorter tag must not match a longer one that starts with it. */
    public function testTagMatchingIsExact(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'trek@example.tld', ['AmTrek']);

        self::assertSame(0, $this->resolver()->count($audience, [['field' => 'tag', 'op' => 'has', 'value' => 'Am']]));
        self::assertSame(1, $this->resolver()->count($audience, [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]));
    }

    /**
     * `_` and `%` are LIKE wildcards and legal tag characters at once. Without an
     * escape they widen the pattern instead of failing, so `AmTrek_2026` would
     * reach `AmTrek-2026` and a segment would silently mail the wrong people.
     */
    public function testATagHoldingLikeWildcardsMatchesItselfOnly(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'underscore@example.tld', ['AmTrek_2026']);
        $this->createContact($audience, 'dash@example.tld', ['AmTrek-2026']);
        $this->createContact($audience, 'percent@example.tld', ['100%Trek']);
        $this->createContact($audience, 'literal@example.tld', ['100Trek']);

        $underscore = $this->resolver()->contacts($audience, [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek_2026']]);
        self::assertCount(1, $underscore);
        self::assertSame('underscore@example.tld', $underscore[0]->email);

        $percent = $this->resolver()->contacts($audience, [['field' => 'tag', 'op' => 'has', 'value' => '100%Trek']]);
        self::assertCount(1, $percent);
        self::assertSame('percent@example.tld', $percent[0]->email);

        // And the escape character itself is not a way back out of the escaping.
        self::assertSame(0, $this->resolver()->count($audience, [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek!_2026']]));
    }

    /**
     * Either of two tags, but only among the customers — the shape a flat rule
     * has no room for, and the one two campaigns do not replace: a contact
     * carrying both tags would be in both and be mailed twice.
     */
    public function testAGroupMayBeNestedInsideAnAndedRule(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'vip@example.tld', ['AmTrek'], ['lastBoughtProduct' => 'tmb']);
        $this->createContact($audience, 'pinned@example.tld', ['AmBivouac'], ['lastBoughtProduct' => 'gr54']);
        $this->createContact($audience, 'browsing@example.tld', ['AmTrek']);
        $this->createContact($audience, 'other@example.tld', ['AmOther'], ['lastBoughtProduct' => 'tmb']);

        $contacts = $this->resolver()->contacts($audience, [
            ['field' => 'prop.lastBoughtProduct', 'op' => 'isSet'],
            ['any' => [
                ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
                ['field' => 'tag', 'op' => 'has', 'value' => 'AmBivouac'],
            ]],
        ]);

        self::assertSame(
            ['pinned@example.tld', 'vip@example.tld'],
            $this->sortedEmails($contacts),
        );
    }

    /**
     * The property the whole sending side rests on: the audience and the
     * subscribed status are ANDed with the rule as a whole, so no amount of
     * nesting reaches past them.
     */
    public function testNestingNeverWidensPastTheGuards(): void
    {
        $audience = $this->createAudience();
        $other = $this->createAudience();
        $this->createContact($audience, 'mine@example.tld', ['AmTrek']);
        $this->createContact($other, 'theirs@example.tld', ['AmTrek']);

        $gone = $this->createContact($audience, 'gone@example.tld', ['AmTrek']);
        $gone->unsubscribe();

        $this->entityManager->flush();

        $contacts = $this->resolver()->contacts($audience, ['any' => [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
            ['any' => [
                ['field' => 'tag', 'op' => 'has', 'value' => 'AmBivouac'],
                ['field' => 'locale', 'op' => '!=', 'value' => 'zz'],
            ]],
        ]]);

        self::assertSame(['mine@example.tld'], $this->sortedEmails($contacts));
    }

    /**
     * @param list<Contact> $contacts
     *
     * @return list<string>
     */
    private function sortedEmails(array $contacts): array
    {
        $emails = array_map(static fn (Contact $contact): string => $contact->identifier(), $contacts);
        sort($emails);

        return $emails;
    }

    public function testRegisteredAtOlderAndNewerThan(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'old@example.tld', registeredAt: new DateTimeImmutable('-30 days'));
        $this->createContact($audience, 'fresh@example.tld', registeredAt: new DateTimeImmutable('-1 hour'));

        $old = $this->resolver()->contacts($audience, [['field' => 'createdAt', 'op' => 'olderThan', 'value' => '7d']]);
        self::assertCount(1, $old);
        self::assertSame('old@example.tld', $old[0]->email);

        $fresh = $this->resolver()->contacts($audience, [['field' => 'createdAt', 'op' => 'newerThan', 'value' => '7d']]);
        self::assertCount(1, $fresh);
        self::assertSame('fresh@example.tld', $fresh[0]->email);
    }

    public function testCustomPropertyEquality(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'buyer@example.tld', customProperties: ['lastBoughtProduct' => 'tmb']);
        $this->createContact($audience, 'other@example.tld', customProperties: ['lastBoughtProduct' => 'gr54']);
        $this->createContact($audience, 'unknown@example.tld');

        $match = $this->resolver()->contacts($audience, [
            ['field' => 'prop.lastBoughtProduct', 'op' => '=', 'value' => 'tmb'],
        ]);

        self::assertCount(1, $match);
        self::assertSame('buyer@example.tld', $match[0]->email);
    }

    /** A missing property is unknown, not "different from tmb". */
    public function testCustomPropertyInequalityIgnoresContactsWithoutTheProperty(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'buyer@example.tld', customProperties: ['lastBoughtProduct' => 'tmb']);
        $this->createContact($audience, 'other@example.tld', customProperties: ['lastBoughtProduct' => 'gr54']);
        $this->createContact($audience, 'unknown@example.tld');

        $match = $this->resolver()->contacts($audience, [
            ['field' => 'prop.lastBoughtProduct', 'op' => '!=', 'value' => 'tmb'],
        ]);

        self::assertCount(1, $match);
        self::assertSame('other@example.tld', $match[0]->email);
    }

    public function testCustomPropertyPresence(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'known@example.tld', customProperties: ['lastBoughtProduct' => 'tmb']);
        $this->createContact($audience, 'unknown@example.tld');

        $isSet = $this->resolver()->contacts($audience, [['field' => 'prop.lastBoughtProduct', 'op' => 'isSet']]);
        self::assertCount(1, $isSet);
        self::assertSame('known@example.tld', $isSet[0]->email);

        $isNotSet = $this->resolver()->contacts($audience, [['field' => 'prop.lastBoughtProduct', 'op' => 'isNotSet']]);
        self::assertCount(1, $isNotSet);
        self::assertSame('unknown@example.tld', $isNotSet[0]->email);
    }

    public function testCustomPropertyDateTakesDurations(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'dormant@example.tld', customProperties: ['lastSeenAt' => $this->atom('-90 days')]);
        $this->createContact($audience, 'active@example.tld', customProperties: ['lastSeenAt' => $this->atom('-2 days')]);

        $stale = $this->resolver()->contacts($audience, [
            ['field' => 'prop.lastSeenAt', 'op' => 'olderThan', 'value' => '30d'],
        ]);
        self::assertCount(1, $stale);
        self::assertSame('dormant@example.tld', $stale[0]->email);

        $recent = $this->resolver()->contacts($audience, [
            ['field' => 'prop.lastSeenAt', 'op' => 'newerThan', 'value' => '30d'],
        ]);
        self::assertCount(1, $recent);
        self::assertSame('active@example.tld', $recent[0]->email);
    }

    /** NULL compares to nothing: never having been seen is not being old. */
    public function testACustomPropertyNobodyWroteIsOnNeitherSideOfADuration(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'dormant@example.tld', customProperties: ['lastSeenAt' => $this->atom('-90 days')]);
        $this->createContact($audience, 'never@example.tld');

        self::assertSame(1, $this->resolver()->count($audience, [
            ['field' => 'prop.lastSeenAt', 'op' => 'olderThan', 'value' => '30d'],
        ]));

        // Saying "dormant or never seen" is the caller's to write, and it does.
        self::assertSame(2, $this->resolver()->count($audience, [
            ['any' => [
                ['field' => 'prop.lastSeenAt', 'op' => 'olderThan', 'value' => '30d'],
                ['field' => 'prop.lastSeenAt', 'op' => 'isNotSet'],
            ]],
        ]));
    }

    /** The comparison is lexical, so it only holds while the offsets agree. */
    public function testCustomPropertyDurationsComparePastAYearBoundary(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'old@example.tld', customProperties: ['lastSeenAt' => '2025-12-30T09:00:00+00:00']);
        $this->createContact($audience, 'new@example.tld', customProperties: ['lastSeenAt' => $this->atom('-1 day')]);

        $stale = $this->resolver()->contacts($audience, [
            ['field' => 'prop.lastSeenAt', 'op' => 'olderThan', 'value' => '2w'],
        ]);

        self::assertCount(1, $stale);
        self::assertSame('old@example.tld', $stale[0]->email);
    }

    public function testConditionsAreAnded(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'both@example.tld', ['AmTrek'], ['lastBoughtProduct' => 'tmb']);
        $this->createContact($audience, 'tagOnly@example.tld', ['AmTrek']);
        $this->createContact($audience, 'propOnly@example.tld', [], ['lastBoughtProduct' => 'tmb']);

        $match = $this->resolver()->contacts($audience, [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
            ['field' => 'prop.lastBoughtProduct', 'op' => '=', 'value' => 'tmb'],
        ]);

        self::assertCount(1, $match);
        self::assertSame('both@example.tld', $match[0]->email);
    }

    /**
     * The reason `any` exists: two campaigns do not replace it — a contact
     * carrying both tags would be in both, and be mailed twice.
     */
    public function testAnAnyGroupTakesEitherConditionAndCountsNobodyTwice(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'trek@example.tld', ['AmTrek']);
        $this->createContact($audience, 'vip@example.tld', ['VIP']);
        $this->createContact($audience, 'both@example.tld', ['AmTrek', 'VIP']);
        $this->createContact($audience, 'neither@example.tld');

        $criteria = ['any' => [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
            ['field' => 'tag', 'op' => 'has', 'value' => 'VIP'],
        ]];

        self::assertSame(3, $this->resolver()->count($audience, $criteria));
    }

    /** `any` widens who is reached, never past the audience or the subscribed status. */
    public function testAnAnyGroupNeverWidensPastTheGuards(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'subscribed@example.tld', ['AmTrek']);
        $this->createContact($this->createAudience(), 'elsewhere@example.tld', ['AmTrek']);

        $this->createContact($audience, 'gone@example.tld', ['AmTrek'])->unsubscribe();
        $this->entityManager->flush();

        $match = $this->resolver()->contacts($audience, ['any' => [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
            ['field' => 'tag', 'op' => 'has', 'value' => 'VIP'],
        ]]);

        self::assertCount(1, $match);
        self::assertSame('subscribed@example.tld', $match[0]->email);
    }

    /**
     * The plain query still holds everybody who consented — a contact known
     * only by phone is listed, exported and segmented; the mailable one is what
     * anything that sends goes through.
     */
    public function testMailableNarrowsToContactsHoldingAnAddress(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $this->createPhoneContact($audience, '+33612345678');

        self::assertSame(2, $this->resolver()->count($audience, []));
        self::assertSame(1, $this->resolver()->countMailable($audience, []));
        self::assertSame(['reader@example.tld'], $this->sortedEmails($this->resolver()->mailableContacts($audience, [])));
    }

    public function testAnAbsentIdentifierIsASegmentOfItsOwn(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $phoneOnly = $this->createPhoneContact($audience, '+33612345678');

        self::assertSame(
            [$phoneOnly->identifier()],
            $this->sortedEmails($this->resolver()->contacts($audience, [['field' => 'email', 'op' => 'isNotSet']])),
        );
        self::assertSame(
            [$phoneOnly->identifier()],
            $this->sortedEmails($this->resolver()->contacts($audience, [['field' => 'phone', 'op' => 'isSet']])),
        );
        self::assertSame(1, $this->resolver()->count($audience, [['field' => 'email', 'op' => 'isSet']]));
    }

    public function testMatchesAgreesWithTheListForOneContact(): void
    {
        $audience = $this->createAudience();
        $trekker = $this->createContact($audience, 'trek@example.tld', ['AmTrek']);
        $other = $this->createContact($audience, 'other@example.tld');
        $criteria = [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']];

        self::assertTrue($this->resolver()->matches($trekker, $criteria));
        self::assertFalse($this->resolver()->matches($other, $criteria));
    }

    public function testMatchesIsFalseForAnUnsubscribedContact(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'gone@example.tld', ['AmTrek']);
        $contact->unsubscribe();

        $this->entityManager->flush();

        self::assertFalse($this->resolver()->matches($contact, [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]));
    }

    public function testLocaleFilter(): void
    {
        $audience = $this->createAudience();
        $french = new Contact($audience, 'fr@example.tld');
        $french->locale = 'fr';
        $french->optIn(false);

        $this->entityManager->persist($french);
        $this->createContact($audience, 'en@example.tld');
        $this->entityManager->flush();

        $match = $this->resolver()->contacts($audience, [['field' => 'locale', 'op' => '=', 'value' => 'fr']]);

        self::assertCount(1, $match);
        self::assertSame('fr@example.tld', $match[0]->email);
    }

    public function testAnInvalidSegmentThrowsRatherThanSilentlyWidening(): void
    {
        $audience = $this->createAudience();

        $this->expectException(SegmentException::class);

        $this->resolver()->count($audience, [['field' => 'unknown', 'op' => '=', 'value' => 'x']]);
    }

    public function testLimitIsHonoured(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'a@example.tld');
        $this->createContact($audience, 'b@example.tld');
        $this->createContact($audience, 'c@example.tld');

        self::assertCount(2, $this->resolver()->contacts($audience, [], 2));
    }

    private function atom(string $modifier): string
    {
        return new DateTimeImmutable()->modify($modifier)->format(DateTimeInterface::ATOM);
    }
}
