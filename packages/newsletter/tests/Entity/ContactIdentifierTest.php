<?php

namespace Pushword\Newsletter\Tests\Entity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A contact is keyed on an address or on a number, and `subscribed` stops
 * meaning "can be mailed" the moment the second kind exists.
 */
#[Group('integration')]
final class ContactIdentifierTest extends AbstractNewsletterTestCase
{
    public function testNeitherAnAddressNorANumberIsInvalid(): void
    {
        $contact = new Contact($this->createAudience());

        self::assertFalse($contact->hasIdentifier());
        self::assertCount(1, $this->validator()->validate($contact));
    }

    #[DataProvider('identifierProvider')]
    public function testEitherOneIsEnough(?string $email, ?string $phone): void
    {
        $contact = new Contact($this->createAudience(), $email, $phone);

        self::assertTrue($contact->hasIdentifier());
        self::assertCount(0, $this->validator()->validate($contact));
    }

    /** @return iterable<string, array{?string, ?string}> */
    public static function identifierProvider(): iterable
    {
        yield 'email only' => ['someone@example.tld', null];
        yield 'phone only' => [null, '+33612345678'];
        yield 'both' => ['someone@example.tld', '+33612345678'];
    }

    public function testAPhoneIsKeptAsDigitsAndALeadingPlus(): void
    {
        $contact = new Contact($this->createAudience(), null, ' +33 (0)6 12.34-56 78 ');

        self::assertSame('+330612345678', $contact->phone);
    }

    public function testAnEmptyIdentifierIsNullRatherThanBlank(): void
    {
        $contact = new Contact($this->createAudience(), '  ', ' - ');

        self::assertNull($contact->email);
        self::assertNull($contact->phone);
    }

    public function testMailableIsSubscribedAndHoldingAnAddress(): void
    {
        $audience = $this->createAudience();

        $subscribed = $this->createContact($audience, 'in@example.tld');
        self::assertTrue($subscribed->isMailable());

        $pending = $this->createContact($audience, 'pending@example.tld', subscribed: false);
        self::assertFalse($pending->isMailable());

        $unsubscribed = $this->createContact($audience, 'left@example.tld');
        $unsubscribed->unsubscribe();
        self::assertFalse($unsubscribed->isMailable());

        $bounced = $this->createContact($audience, 'dead@example.tld');
        $bounced->markBounced();
        self::assertFalse($bounced->isMailable());
    }

    public function testAPhoneOnlyContactConsentsWithoutBeingMailable(): void
    {
        $contact = $this->createPhoneContact($this->createAudience(), '+33612345678');

        self::assertTrue($contact->isSubscribed());
        self::assertFalse($contact->isMailable());
    }

    /** There is no confirmation link to click when there is no mail to put it in. */
    public function testDoubleOptInIsSkippedWithoutAnAddress(): void
    {
        $contact = new Contact($this->createAudience(), null, '+33612345678');

        $contact->optIn(true);

        self::assertSame(ContactStatus::Subscribed, $contact->status);
        self::assertNotNull($contact->confirmedAt);
    }

    public function testDoubleOptInStillHoldsForAnAddress(): void
    {
        $contact = new Contact($this->createAudience(), 'someone@example.tld');

        $contact->optIn(true);

        self::assertSame(ContactStatus::Pending, $contact->status);
    }

    public function testAPhoneNamesTheContactWhenNothingElseDoes(): void
    {
        $audience = $this->createAudience();

        self::assertSame('+33612345678', new Contact($audience, null, '+33612345678')->identifier());
        self::assertSame('Test <+33612345678>', (string) $this->createPhoneContact($audience, '+33612345678'));
    }

    /** Two people known only by phone are two rows, and both hold a null email. */
    public function testManyContactsMayShareTheAbsenceOfAnAddress(): void
    {
        $audience = $this->createAudience();

        $this->createPhoneContact($audience, '+33612345678');
        $this->createPhoneContact($audience, '+33698765432');

        self::assertCount(2, $this->entityManager->getRepository(Contact::class)->findBy(['audience' => $audience]));
    }

    private function validator(): ValidatorInterface
    {
        return self::getContainer()->get(ValidatorInterface::class);
    }
}
