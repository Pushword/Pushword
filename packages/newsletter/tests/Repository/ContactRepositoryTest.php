<?php

namespace Pushword\Newsletter\Tests\Repository;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;

/**
 * The sibling lookups key on the address, which is what makes two rows the same
 * person. Asked about a contact who has none, they must answer nothing — read as
 * a plain `WHERE email = NULL` they would instead match every phone-only contact
 * in the database, and show a hundred strangers as one person's subscriptions.
 */
#[Group('integration')]
final class ContactRepositoryTest extends AbstractNewsletterTestCase
{
    public function testNoAddressMeansNoSubscriptionList(): void
    {
        $audience = $this->createAudience();
        $this->createPhoneContact($audience, '+33612345678');
        $this->createPhoneContact($audience, '+33698765432');

        self::assertSame([], $this->repository()->findAllByEmail(null));
        self::assertSame([], $this->repository()->findAllByEmail('  '));
    }

    public function testNoAddressMeansNoSiblings(): void
    {
        $audience = $this->createAudience();
        $this->createPhoneContact($audience, '+33698765432');
        $contact = $this->createPhoneContact($audience, '+33612345678');

        self::assertSame([], $this->repository()->findSubscribedSiblings($contact));
    }

    public function testAnAddressStillFindsItsOtherSubscriptions(): void
    {
        $first = $this->createAudience();
        $second = $this->createAudience();
        $this->createContact($first, 'both@example.tld');
        $this->createContact($second, 'both@example.tld');
        $this->createPhoneContact($first, '+33612345678');

        self::assertCount(2, $this->repository()->findAllByEmail('both@example.tld'));
    }

    public function testANumberIsFoundHoweverItIsSpaced(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createPhoneContact($audience, '+33612345678');

        self::assertSame($contact->id, $this->repository()->findOneByPhone($audience, ' +33 6 12.34-56 78 ')?->id);
        self::assertNull($this->repository()->findOneByPhone($audience, '   '));
    }

    /**
     * No country is inferred, so a trunk prefix is kept: `+33 (0)6…` is a
     * different string from `+336…` and stays a different row. Guessing which
     * one the site meant is how an import silently merges two people.
     */
    public function testATrunkPrefixIsNotGuessedAway(): void
    {
        $audience = $this->createAudience();
        $this->createPhoneContact($audience, '+33612345678');

        self::assertNull($this->repository()->findOneByPhone($audience, '+33 (0)6 12 34 56 78'));
    }

    public function testMailableIsCountedApartFromSubscribed(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'in@example.tld');
        $this->createContact($audience, 'pending@example.tld', subscribed: false);
        $this->createPhoneContact($audience, '+33612345678');

        self::assertSame(2, $this->repository()->countByStatus($audience)['subscribed']);
        self::assertSame(1, $this->repository()->countMailable($audience));
    }

    private function repository(): ContactRepository
    {
        return self::getContainer()->get(ContactRepository::class);
    }
}
