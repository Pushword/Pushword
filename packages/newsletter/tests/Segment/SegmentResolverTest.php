<?php

namespace Pushword\Newsletter\Tests\Segment;

use DateTimeImmutable;
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
        self::assertSame('subscribed@example.tld', $contacts[0]->getEmail());
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
        self::assertSame('trek@example.tld', $has[0]->getEmail());

        $hasNot = $this->resolver()->contacts($audience, [['field' => 'tag', 'op' => 'hasNot', 'value' => 'AmTrek']]);
        self::assertCount(1, $hasNot);
        self::assertSame('other@example.tld', $hasNot[0]->getEmail());
    }

    /** A shorter tag must not match a longer one that starts with it. */
    public function testTagMatchingIsExact(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'trek@example.tld', ['AmTrek']);

        self::assertSame(0, $this->resolver()->count($audience, [['field' => 'tag', 'op' => 'has', 'value' => 'Am']]));
        self::assertSame(1, $this->resolver()->count($audience, [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]));
    }

    public function testRegisteredAtOlderAndNewerThan(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'old@example.tld', registeredAt: new DateTimeImmutable('-30 days'));
        $this->createContact($audience, 'fresh@example.tld', registeredAt: new DateTimeImmutable('-1 hour'));

        $old = $this->resolver()->contacts($audience, [['field' => 'createdAt', 'op' => 'olderThan', 'value' => '7d']]);
        self::assertCount(1, $old);
        self::assertSame('old@example.tld', $old[0]->getEmail());

        $fresh = $this->resolver()->contacts($audience, [['field' => 'createdAt', 'op' => 'newerThan', 'value' => '7d']]);
        self::assertCount(1, $fresh);
        self::assertSame('fresh@example.tld', $fresh[0]->getEmail());
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
        self::assertSame('buyer@example.tld', $match[0]->getEmail());
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
        self::assertSame('other@example.tld', $match[0]->getEmail());
    }

    public function testCustomPropertyPresence(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'known@example.tld', customProperties: ['lastBoughtProduct' => 'tmb']);
        $this->createContact($audience, 'unknown@example.tld');

        $isSet = $this->resolver()->contacts($audience, [['field' => 'prop.lastBoughtProduct', 'op' => 'isSet']]);
        self::assertCount(1, $isSet);
        self::assertSame('known@example.tld', $isSet[0]->getEmail());

        $isNotSet = $this->resolver()->contacts($audience, [['field' => 'prop.lastBoughtProduct', 'op' => 'isNotSet']]);
        self::assertCount(1, $isNotSet);
        self::assertSame('unknown@example.tld', $isNotSet[0]->getEmail());
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
        self::assertSame('both@example.tld', $match[0]->getEmail());
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
        self::assertSame('subscribed@example.tld', $match[0]->getEmail());
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
        $french->setLocale('fr')->optIn(false);
        $this->entityManager->persist($french);
        $this->createContact($audience, 'en@example.tld');
        $this->entityManager->flush();

        $match = $this->resolver()->contacts($audience, [['field' => 'locale', 'op' => '=', 'value' => 'fr']]);

        self::assertCount(1, $match);
        self::assertSame('fr@example.tld', $match[0]->getEmail());
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
}
