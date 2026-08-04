<?php

namespace Pushword\Newsletter\Tests\Controller\Api;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Service\AutomationRunner;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class NewsletterApiTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    private string $token = '';

    private string $userEmail = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = bin2hex(random_bytes(32));
        $this->userEmail = 'newsletter-api-'.uniqid().'@example.tld';
        // The app may replace the user entity; ask the repository which class it manages.
        $userClass = $this->userRepository()->getClassName();
        $user = new $userClass();
        $user->email = $this->userEmail;
        $user->setPassword('hashed');
        $user->apiToken = $this->token;
        $user->setRoles(['ROLE_EDITOR']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $user = $this->userRepository()->findOneBy(['email' => $this->userEmail]);
        if ($user instanceof User) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testAnonymousAccessIsRefused(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/newsletter/contact');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testCreatingAnAudienceThroughTheApi(): void
    {
        $body = $this->request(Request::METHOD_POST, '/api/newsletter/audience', [
            'slug' => 'api-'.bin2hex(random_bytes(6)),
            'name' => 'Bootstrapped',
            'mainHost' => 'localhost.dev',
            'fromEmail' => 'News@Localhost.dev',
            'interests' => ['AmTrek'],
            'rateSeconds' => 60,
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $this->trackAudience($this->id($body));
        self::assertTrue($body['requireDoubleOptIn'], 'consent is asked for unless the caller says otherwise');
        self::assertSame('news@localhost.dev', $body['fromEmail']);
        self::assertSame(['AmTrek'], $body['interests']);
        self::assertSame(60, $body['rateSeconds']);
    }

    public function testAnAudienceCarriesItsSenderIdentityAndOptInRule(): void
    {
        $body = $this->request(Request::METHOD_POST, '/api/newsletter/audience', [
            'slug' => 'api-'.bin2hex(random_bytes(6)),
            'name' => 'Altimood',
            'mainHost' => 'localhost.dev',
            'fromName' => 'Robin',
            'fromEmail' => 'news@localhost.dev',
            'replyTo' => 'Hello@Localhost.dev',
            'requireDoubleOptIn' => false,
        ]);

        $this->trackAudience($this->id($body));
        self::assertSame('Altimood', $body['name']);
        self::assertSame('Robin', $body['fromName']);
        self::assertSame('hello@localhost.dev', $body['replyTo']);
        self::assertFalse($body['requireDoubleOptIn'], 'an already-consenting base is imported without a second ask');
    }

    public function testAnUnknownAudienceSlugIsNotFound(): void
    {
        $this->request(Request::METHOD_GET, '/api/newsletter/audience/no-such-audience');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /** Everything else names an audience by slug, so two of them cannot share one. */
    public function testAnAudienceSlugIsTakenOnlyOnce(): void
    {
        $audience = $this->createAudience();

        $this->request(Request::METHOD_POST, '/api/newsletter/audience', [
            'slug' => $audience->slug,
            'mainHost' => 'localhost.dev',
            'fromEmail' => 'news@localhost.dev',
        ]);

        self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
    }

    /** An unknown host falls back to the default site: the links would point at another brand. */
    public function testAnAudienceOnAnUnknownHostIsRefused(): void
    {
        $this->request(Request::METHOD_POST, '/api/newsletter/audience', [
            'slug' => 'api-'.bin2hex(random_bytes(6)),
            'mainHost' => 'unknown.example',
            'fromEmail' => 'news@localhost.dev',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /** An alias is stored as its main host, the one sibling lists are matched on. */
    public function testAnAudienceHostAliasIsStoredAsTheMainHost(): void
    {
        $body = $this->request(Request::METHOD_POST, '/api/newsletter/audience', [
            'slug' => 'api-'.bin2hex(random_bytes(6)),
            'mainHost' => 'localhost',
            'fromEmail' => 'news@localhost.dev',
        ]);

        $this->trackAudience($this->id($body));
        self::assertSame('localhost.dev', $body['mainHost']);
    }

    public function testAnAudienceNeedsAHostAndASender(): void
    {
        $body = $this->request(Request::METHOD_POST, '/api/newsletter/audience', [
            'slug' => 'api-'.bin2hex(random_bytes(6)),
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame('validation', $body['error']);
    }

    public function testAnAudienceReportsItsContactsPerStatus(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'in@example.tld');
        $this->createContact($audience, 'waiting@example.tld', subscribed: false);

        $body = $this->request(Request::METHOD_GET, '/api/newsletter/audience/'.$audience->slug);

        self::assertSame(
            ['pending' => 1, 'subscribed' => 1, 'unsubscribed' => 0, 'bounced' => 0, 'mailable' => 1],
            $body['contacts'],
        );
    }

    /** Consented and reachable are two numbers, and reporting one as the other overstates a send. */
    public function testAnAudienceCountsTheMailableApartFromTheSubscribed(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'in@example.tld');
        $this->createPhoneContact($audience, '+33612345678');

        $body = $this->request(Request::METHOD_GET, '/api/newsletter/audience/'.$audience->slug);
        $contacts = $body['contacts'];

        self::assertIsArray($contacts);
        self::assertSame(2, $contacts['subscribed']);
        self::assertSame(1, $contacts['mailable']);
    }

    public function testPatchingAnAudience(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/audience/'.$audience->slug, [
            'rateSeconds' => 5,
            'interests' => ['AmTrek'],
            'utmSource' => 'Ma Newsletter',
        ]);

        self::assertSame(5, $body['rateSeconds']);
        self::assertSame(['AmTrek'], $body['interests']);
        self::assertSame('ma-newsletter', $body['utmSource']);
    }

    /** Consent records are not something one HTTP call gets to drop by the side. */
    public function testDeletingAnAudienceIsRefusedWhileItHoldsContacts(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'held@example.tld');

        $body = $this->request(Request::METHOD_DELETE, '/api/newsletter/audience/'.$audience->slug);

        self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
        self::assertSame(1, $body['contacts']);
    }

    public function testDeletingAnEmptyAudience(): void
    {
        $audience = $this->createAudience();

        $this->request(Request::METHOD_DELETE, '/api/newsletter/audience/'.$audience->slug);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->entityManager->getRepository(Audience::class)->findOneBy(['slug' => $audience->slug]));
    }

    public function testListingAudiencesIsScopedByHost(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_GET, '/api/newsletter/audience?host='.$audience->mainHost);
        $items = $body['items'];
        self::assertIsArray($items);
        self::assertContains($audience->slug, array_column($items, 'slug'));

        $body = $this->request(Request::METHOD_GET, '/api/newsletter/audience?host=pushword.piedweb.com');
        $items = $body['items'];
        self::assertIsArray($items);
        self::assertNotContains($audience->slug, array_column($items, 'slug'));
    }

    public function testCreatingAContactHonoursTheAudienceDoubleOptIn(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->slug,
            'email' => 'api@example.tld',
            'name' => 'Robin',
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        self::assertSame('pending', $body['status']);
        self::assertSame('api', $body['source']);
        self::assertEmailCount(1);
    }

    /** Importing a base that already consented must not replay the confirmation ask. */
    public function testCreatingAContactAsSubscribedSkipsTheConfirmation(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->slug,
            'email' => 'imported@example.tld',
            'status' => 'subscribed',
        ]);

        self::assertSame('subscribed', $body['status']);
        self::assertEmailCount(0);
    }

    public function testCreatingTwiceUpdatesRatherThanDuplicates(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->slug,
            'email' => 'twice@example.tld',
            'name' => 'First',
        ]);

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->slug,
            'email' => 'twice@example.tld',
            'name' => 'Second',
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame('Second', $body['name']);
        self::assertCount(1, $this->entityManager->getRepository(Contact::class)->findBy(['email' => 'twice@example.tld']));
    }

    public function testUnknownAudienceIsRefused(): void
    {
        $this->request(Request::METHOD_POST, '/api/newsletter/contact', ['audience' => 'nope', 'email' => 'a@b.tld']);

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testInvalidEmailIsRefused(): void
    {
        $audience = $this->createAudience();

        $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->slug,
            'email' => 'not-an-email',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * The public form stays email-only on purpose. A contact known by phone
     * alone arrives here, or through the admin's opt-in — from a booking taken
     * over the phone, a paper form, a number written down on site.
     */
    public function testCreatingAContactOnAPhoneAlone(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->slug,
            'phone' => '+33 6 12 34 56 78',
            'name' => 'Called',
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        self::assertNull($body['email']);
        self::assertSame('+33612345678', $body['phone']);
        // Nothing to confirm by mail, so consent is recorded straight away —
        // and no confirmation was attempted.
        self::assertSame('subscribed', $body['status']);
        self::assertFalse($body['mailable']);
        self::assertEmailCount(0);
    }

    public function testAPhoneUpsertKeysOnTheNumber(): void
    {
        $audience = $this->createAudience();
        $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->slug,
            'phone' => '+33612345678',
            'name' => 'First',
        ]);

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->slug,
            'phone' => '+33 6 12 34 56 78',
            'name' => 'Second',
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame('Second', $body['name']);
        self::assertCount(1, $this->entityManager->getRepository(Contact::class)->findBy(['phone' => '+33612345678']));
    }

    public function testNeitherAnAddressNorANumberIsRefused(): void
    {
        $audience = $this->createAudience();

        $this->request(Request::METHOD_POST, '/api/newsletter/contact', ['audience' => $audience->slug, 'name' => 'Nobody']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testPatchingAPhoneOntoAnEmailOnlyContact(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'both@example.tld');

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/contact/'.$contact->id, [
            'phone' => '06 12 34 56 78',
        ]);

        self::assertSame('0612345678', $body['phone']);
        self::assertSame('both@example.tld', $body['email']);
        self::assertTrue($body['mailable']);
    }

    /**
     * The two rows may well be one person, and that is exactly why somebody is
     * writing the number — but joining them means deciding which consent record
     * survives and which token the live unsubscribe links keep working with. It
     * is refused unless the caller asks for it, with the row that holds it named.
     */
    public function testUpsertingANumberSomebodyElseHoldsIsRefused(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $phoneOnly = $this->createPhoneContact($audience, '+33612345678');
        $this->createContact($audience, 'reader@example.tld');

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->slug,
            'email' => 'reader@example.tld',
            'phone' => '+33 6 12 34 56 78',
        ]);

        self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
        self::assertIsString($body['error']);
        self::assertStringContainsString((string) $phoneOnly->id, $body['error']);
    }

    /** The same wall from the other side: PATCH is validated before it reaches the driver. */
    public function testPatchingANumberSomebodyElseHoldsIsRefused(): void
    {
        $audience = $this->createAudience();
        $this->createPhoneContact($audience, '+33612345678');
        $contact = $this->createContact($audience, 'reader@example.tld');

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/contact/'.$contact->id, [
            'phone' => '+33612345678',
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame('validation', $body['error']);
    }

    /**
     * `?merge=true` is the caller saying the two rows are one person. The row
     * holding the address survives, keeping its id and its links; the number
     * moves onto it and the other row goes.
     */
    public function testUpsertingWithMergeJoinsTheTwoRows(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $phoneOnly = $this->createPhoneContact($audience, '+33612345678');
        $reader = $this->createContact($audience, 'reader@example.tld');
        $absorbedId = $phoneOnly->id;

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact?merge=true', [
            'audience' => $audience->slug,
            'email' => 'reader@example.tld',
            'phone' => '+33 6 12 34 56 78',
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame($reader->id, $body['id']);
        self::assertSame('+33612345678', $body['phone']);
        self::assertNull($this->entityManager->getRepository(Contact::class)->find($absorbedId));
    }

    /**
     * Nothing to join: no row holds the address yet, so the row holding the
     * number is the person and the address is what it was missing.
     */
    public function testUpsertingWithMergeGivesTheAddressToTheNumberItKnows(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $phoneOnly = $this->createPhoneContact($audience, '+33612345678');

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact?merge=true', [
            'audience' => $audience->slug,
            'email' => 'called@example.tld',
            'phone' => '+33612345678',
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame($phoneOnly->id, $body['id']);
        self::assertSame('called@example.tld', $body['email']);
        self::assertTrue($body['mailable']);
    }

    /** Two addressed rows stay two people, whatever the caller asks for. */
    public function testMergeCannotJoinTwoAddressedRows(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $holder = $this->createContact($audience, 'first@example.tld');
        $holder->phone = '+33612345678';

        $this->entityManager->flush();
        $this->createContact($audience, 'second@example.tld');

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact?merge=true', [
            'audience' => $audience->slug,
            'email' => 'second@example.tld',
            'phone' => '+33612345678',
        ]);

        self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
        self::assertIsString($body['error']);
    }

    /**
     * The number belongs to a row that has an address of its own, and the write
     * carries a third one. Three rows is not a merge anybody asked for.
     */
    public function testMergeRefusesANumberHeldUnderAnotherAddress(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $holder = $this->createContact($audience, 'holder@example.tld');
        $holder->phone = '+33612345678';

        $this->entityManager->flush();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact?merge=true', [
            'audience' => $audience->slug,
            'email' => 'newcomer@example.tld',
            'phone' => '+33612345678',
        ]);

        self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
        self::assertIsString($body['error']);
        self::assertStringContainsString('another address', $body['error']);
        self::assertNull($this->entityManager->getRepository(Contact::class)->findOneBy(['email' => 'newcomer@example.tld']));
    }

    /**
     * A number that reaches the row it is being created on has to be free too:
     * without the check the write reaches the unique index, and a driver
     * exception is not an answer a caller can act on.
     */
    public function testCreatingAContactOnANumberSomebodyElseHoldsIsRefused(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $phoneOnly = $this->createPhoneContact($audience, '+33612345678');

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->slug,
            'email' => 'newcomer@example.tld',
            'phone' => '+33612345678',
        ]);

        self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
        self::assertIsString($body['error']);
        self::assertStringContainsString((string) $phoneOnly->id, $body['error']);
    }

    /** And an address somebody else holds, which is the same rule read the other way. */
    public function testPatchingAnAddressSomebodyElseHoldsIsRefused(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'taken@example.tld');
        $contact = $this->createPhoneContact($audience, '+33612345678');

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/contact/'.$contact->id, [
            'email' => 'taken@example.tld',
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame('validation', $body['error']);
    }

    public function testPatchingANumberWithMergeJoinsTheTwoRows(): void
    {
        $audience = $this->createAudience();
        $phoneOnly = $this->createPhoneContact($audience, '+33612345678');
        $contact = $this->createContact($audience, 'reader@example.tld');
        $absorbedId = $phoneOnly->id;

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/contact/'.$contact->id.'?merge=true', [
            'phone' => '+33612345678',
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame($contact->id, $body['id']);
        self::assertSame('+33612345678', $body['phone']);
        self::assertNull($this->entityManager->getRepository(Contact::class)->find($absorbedId));
    }

    /**
     * Patching an address onto a number answers with another id than the one in
     * the path: the addressed row is the one that survives, whichever side the
     * write came from.
     */
    public function testPatchingAnAddressWithMergeAnswersWithTheAddressedRow(): void
    {
        $audience = $this->createAudience();
        $reader = $this->createContact($audience, 'taken@example.tld');
        $contact = $this->createPhoneContact($audience, '+33612345678');
        $absorbedId = $contact->id;

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/contact/'.$contact->id.'?merge=true', [
            'email' => 'taken@example.tld',
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame($reader->id, $body['id']);
        self::assertSame('+33612345678', $body['phone']);
        self::assertNull($this->entityManager->getRepository(Contact::class)->find($absorbedId));
    }

    /** One write naming two other rows would join three; it is refused rather than guessed at. */
    public function testPatchingWithMergeRefusesToJoinThreeRows(): void
    {
        $audience = $this->createAudience();
        $phoneOnly = $this->createPhoneContact($audience, '+33612345678');
        $addressed = $this->createContact($audience, 'taken@example.tld');
        $contact = $this->createContact($audience, 'third@example.tld');

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/contact/'.$contact->id.'?merge=true', [
            'email' => 'taken@example.tld',
            'phone' => '+33612345678',
        ]);

        self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
        self::assertIsString($body['error']);
        self::assertNotNull($this->entityManager->getRepository(Contact::class)->find($phoneOnly->id));
        self::assertNotNull($this->entityManager->getRepository(Contact::class)->find($addressed->id));
    }

    /** A caller that knows one property must not have to preserve the others. */
    public function testPatchMergesCustomPropertiesAndRemovesOnNull(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'props@example.tld', customProperties: ['plan' => 'gold', 'city' => 'Grenoble']);

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/contact/'.$contact->id, [
            'customProperties' => ['lastBoughtProduct' => 'tmb', 'city' => null],
        ]);

        self::assertSame(['plan' => 'gold', 'lastBoughtProduct' => 'tmb'], $body['customProperties']);
    }

    public function testPatchReplacesTags(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'tags@example.tld', ['AmTrek']);

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/contact/'.$contact->id, [
            'tags' => ['AmClient'],
        ]);

        self::assertSame(['AmClient'], $body['tags']);
    }

    public function testUnsubscribingThroughTheApi(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'gone@example.tld');

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact/'.$contact->id.'/unsubscribe');

        self::assertSame('unsubscribed', $body['status']);
        self::assertSame(ContactStatus::Unsubscribed, $this->reload($contact)->status);
    }

    public function testBouncingThroughTheApi(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'dead@example.tld');

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact/'.$contact->id.'/bounce');

        self::assertSame('bounced', $body['status']);
    }

    public function testListingBySegment(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'trek@example.tld', ['AmTrek']);
        $this->createContact($audience, 'other@example.tld');

        $segment = json_encode([['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]);
        self::assertIsString($segment);

        $body = $this->request(
            Request::METHOD_GET,
            '/api/newsletter/contact?audience='.$audience->slug.'&segment='.urlencode($segment),
        );

        self::assertSame(1, $body['total']);
    }

    public function testAnInvalidSegmentIsAFourHundred(): void
    {
        $audience = $this->createAudience();
        $segment = json_encode([['field' => 'nope', 'op' => '=', 'value' => 'x']]);
        self::assertIsString($segment);

        $this->request(
            Request::METHOD_GET,
            '/api/newsletter/contact?audience='.$audience->slug.'&segment='.urlencode($segment),
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testCampaignCreationReportsHowManyItWouldReach(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'a@example.tld', ['AmTrek']);
        $this->createContact($audience, 'b@example.tld');

        $created = $this->request(Request::METHOD_POST, '/api/newsletter/campaign', [
            'audience' => $audience->slug,
            'subject' => 'Summer',
            'bodyMarkdown' => 'Hi **%name%**',
            'segment' => [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']],
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        self::assertSame('draft', $created['status']);

        $fetched = $this->request(Request::METHOD_GET, '/api/newsletter/campaign/'.$this->id($created));

        self::assertSame(1, $fetched['estimatedRecipients']);
    }

    public function testACampaignSlugIsDerivedOrTakenAsGiven(): void
    {
        $audience = $this->createAudience();

        $derived = $this->request(Request::METHOD_POST, '/api/newsletter/campaign', [
            'audience' => $audience->slug,
            'subject' => 'Nos nouveautés',
        ]);
        $given = $this->request(Request::METHOD_POST, '/api/newsletter/campaign', [
            'audience' => $audience->slug,
            'subject' => 'Nos nouveautés',
            'slug' => 'promo-ete',
        ]);

        self::assertSame('nos-nouveautes', $derived['slug']);
        self::assertSame('promo-ete', $given['slug']);
    }

    public function testAnInvalidCampaignSegmentIsRejected(): void
    {
        $audience = $this->createAudience();

        $this->request(Request::METHOD_POST, '/api/newsletter/campaign', [
            'audience' => $audience->slug,
            'subject' => 'Summer',
            'segment' => [['field' => 'tag', 'op' => 'olderThan', 'value' => '7d']],
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testSendingArmsTheCampaign(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/campaign/'.$campaign->id.'/send');

        self::assertSame('sending', $body['status']);
        $stats = $body['stats'];
        self::assertIsArray($stats);
        self::assertSame(1, $stats['recipients']);
        self::assertEmailCount(0, null, 'the tick delivers, the API only arms');
    }

    /** A caller sending the German translation must not have to resend the other seven. */
    public function testTranslationsRoundTripAndMergePerLocale(): void
    {
        $audience = $this->createAudience();

        $created = $this->request(Request::METHOD_POST, '/api/newsletter/campaign', [
            'audience' => $audience->slug,
            'subject' => 'Hello',
            'bodyMarkdown' => 'Read this.',
            'translations' => [
                'de' => ['subject' => 'Hallo', 'bodyMarkdown' => 'Lies das.'],
                'it' => ['subject' => 'Ciao'],
            ],
        ]);

        self::assertSame([
            'de' => ['subject' => 'Hallo', 'bodyMarkdown' => 'Lies das.'],
            'it' => ['subject' => 'Ciao'],
        ], $created['translations']);

        $id = $created['id'];
        self::assertIsInt($id);

        $patched = $this->request(Request::METHOD_PATCH, '/api/newsletter/campaign/'.$id, [
            'translations' => ['it' => ['subject' => 'Ciao', 'bodyMarkdown' => 'Leggi questo.']],
        ]);

        self::assertSame([
            'de' => ['subject' => 'Hallo', 'bodyMarkdown' => 'Lies das.'],
            'it' => ['subject' => 'Ciao', 'bodyMarkdown' => 'Leggi questo.'],
        ], $patched['translations'], 'a locale left out is kept');

        $dropped = $this->request(Request::METHOD_PATCH, '/api/newsletter/campaign/'.$id, [
            'translations' => ['de' => null],
        ]);

        self::assertSame(['it' => ['subject' => 'Ciao', 'bodyMarkdown' => 'Leggi questo.']], $dropped['translations']);
    }

    public function testAnArmedCampaignCanNoLongerBeEdited(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $this->request(Request::METHOD_POST, '/api/newsletter/campaign/'.$campaign->id.'/send');

        $this->request(Request::METHOD_PATCH, '/api/newsletter/campaign/'.$campaign->id, ['subject' => 'Changed']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testScheduling(): void
    {
        $audience = $this->createAudience();
        $campaign = $this->createCampaign($audience);

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/campaign/'.$campaign->id.'/schedule', [
            'scheduledAt' => '2030-01-01 09:00:00',
        ]);

        self::assertSame('scheduled', $body['status']);
        self::assertSame(CampaignStatus::Scheduled, $this->entityManager->getRepository(Campaign::class)->find($campaign->id)?->status);
    }

    public function testSchedulingNeedsADate(): void
    {
        $audience = $this->createAudience();
        $campaign = $this->createCampaign($audience);

        $this->request(Request::METHOD_POST, '/api/newsletter/campaign/'.$campaign->id.'/schedule', ['scheduledAt' => 'nonsense']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testTestSendTouchesNoCounter(): void
    {
        $audience = $this->createAudience();
        $campaign = $this->createCampaign($audience);

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/campaign/'.$campaign->id.'/test', [
            'emails' => ['me@example.tld', 'broken'],
        ]);

        self::assertSame(['me@example.tld'], $body['sent']);
        self::assertSame(['broken'], $body['failed']);
        self::assertEmailCount(1);
        self::assertSame(0, $this->entityManager->getRepository(Campaign::class)->find($campaign->id)?->sentCount);
    }

    public function testCreatingAnAutomationWithItsSteps(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/automation', [
            'audience' => $audience->slug,
            'name' => 'Bienvenue',
            'steps' => [
                ['delayMinutes' => 0, 'subject' => 'Merci', 'bodyMarkdown' => 'Bienvenue **%name%**'],
                ['delayMinutes' => 2880, 'subject' => 'Deux jours plus tard', 'bodyMarkdown' => 'Encore nous'],
            ],
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $steps = $body['steps'];
        self::assertIsArray($steps);
        self::assertSame([0, 1], array_column($steps, 'position'));
        self::assertSame([0, 2880], array_column($steps, 'delayMinutes'));
    }

    /** The order of the array is the order of the sequence, so a resend rewrites it whole. */
    public function testSendingStepsReplacesTheWholeSequence(): void
    {
        $audience = $this->createAudience();
        $automation = $this->createAutomation($audience, [
            ['delay' => 0, 'subject' => 'One'],
            ['delay' => 60, 'subject' => 'Two'],
        ]);

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/automation/'.$automation->id, [
            'steps' => [['delayMinutes' => 2880, 'subject' => 'Only one now', 'bodyMarkdown' => 'Hi']],
        ]);

        $steps = $body['steps'];
        self::assertIsArray($steps);
        self::assertCount(1, $steps);
        self::assertSame(['position' => 0, 'delayMinutes' => 2880, 'subject' => 'Only one now', 'bodyMarkdown' => 'Hi'], $steps[0]);
    }

    public function testAnInvalidTriggerWhenIsRejected(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/automation', [
            'audience' => $audience->slug,
            'name' => 'Broken',
            'triggerWhen' => [['field' => 'tag', 'op' => 'olderThan', 'value' => '7d']],
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $error = $body['error'];
        self::assertIsString($error);
        self::assertStringStartsWith('triggerWhen:', $error);
    }

    /** The vocabulary follows the source, so the same rule is right or wrong depending on it. */
    public function testAnUnknownSourceIsRejected(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/automation', [
            'audience' => $audience->slug,
            'name' => 'Watches nothing',
            'source' => 'no-such-source',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $error = $body['error'];
        self::assertIsString($error);
        self::assertStringContainsString('Unknown source', $error);
    }

    /** The guard must survive the API: switching a drip on cannot mail the existing base. */
    public function testANewAutomationOnlyEnrollsContactsArrivingAfterIt(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'existing@example.tld', registeredAt: new DateTimeImmutable('-1 hour'));

        $created = $this->request(Request::METHOD_POST, '/api/newsletter/automation', [
            'audience' => $audience->slug,
            'name' => 'Welcome',
            'steps' => [['delayMinutes' => 0, 'subject' => 'Hello']],
        ]);

        $automation = $this->entityManager->getRepository(Automation::class)->find($this->id($created));
        self::assertInstanceOf(Automation::class, $automation);
        self::assertSame(0, $this->enroll($automation));

        $this->createContact($audience, 'newcomer@example.tld');

        self::assertSame(1, $this->enroll($automation));
    }

    public function testAnAutomationReportsItsProgress(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld', ['AmTrek']);
        $this->createContact($audience, 'other@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']], [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
        ]);

        $body = $this->request(Request::METHOD_GET, '/api/newsletter/automation/'.$automation->id);
        self::assertSame(1, $body['waiting'], 'one contact matches the rule and has not been enrolled yet');

        $this->enroll($automation);

        $body = $this->request(Request::METHOD_GET, '/api/newsletter/automation/'.$automation->id);

        self::assertSame(0, $body['waiting'], 'and once enrolled it is handled, not waiting');
        self::assertSame(1, $body['handled']);
        self::assertSame(['active' => 1, 'done' => 0, 'stopped' => 0], $body['stats']);

        // recipientWhen is empty and this source addresses its own contacts, so
        // the reach it reports is the whole audience — the figure a broadcast
        // would use, reported whether or not this automation broadcasts.
        self::assertSame(2, $body['matchingContacts']);
    }

    public function testDisablingThroughTheApiPausesTheDrip(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/automation/'.$automation->id, ['enabled' => false]);

        self::assertFalse($body['enabled']);
        self::assertSame(0, $this->enroll($automation), 'a paused automation enrolls nobody');
    }

    /** Enrollments hang off the automation by a database cascade, which only MariaDB enforces. */
    public function testDeletingAnAutomationTakesItsEnrollmentsWithIt(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);
        $this->enroll($automation);

        $this->request(Request::METHOD_DELETE, '/api/newsletter/automation/'.$automation->id);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
    }

    public function testCreatingAPageAutomation(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/automation', [
            'audience' => $audience->slug,
            'name' => 'New articles',
            'source' => 'page',
            'hosts' => ['localhost.dev'],
            'triggerWhen' => [['field' => 'slug', 'op' => 'startsWith', 'value' => 'blog/']],
            'steps' => [[
                'delayMinutes' => 1440,
                'subject' => 'New article: {{ page.h1 }}',
                'bodyMarkdown' => 'Read [{{ page.h1 }}]({{ page.url }}).',
            ]],
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        self::assertSame('page', $body['source']);
        self::assertSame(['localhost.dev'], $body['hosts']);
        self::assertNotNull($body['activeFrom'], 'an automation created over the API cannot mail a back catalogue either');

        $steps = $body['steps'];
        self::assertIsArray($steps);
        self::assertSame([1440], array_column($steps, 'delayMinutes'));
    }

    /**
     * A rule sent as a group comes back as one. Stored flat it would read as an
     * `all`, and the automation would quietly stop matching what it was created for.
     */
    public function testAnAnyGroupSurvivesTheApiRoundTrip(): void
    {
        $audience = $this->createAudience();
        $rule = ['any' => [
            ['field' => 'tag', 'op' => 'has', 'value' => 'blog'],
            ['field' => 'ancestor', 'op' => '=', 'value' => 'blog'],
        ]];

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/automation', [
            'audience' => $audience->slug,
            'name' => 'Either axis',
            'source' => 'page',
            'triggerWhen' => $rule,
            'steps' => [['delayMinutes' => 0, 'subject' => 'New article: {{ page.h1 }}']],
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        self::assertSame($rule, $body['triggerWhen']);
    }

    /** The page grammar is not the contact one; mixing them up must be said, not ignored. */
    public function testAContactFieldInAPageRuleIsRejected(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/automation', [
            'audience' => $audience->slug,
            'name' => 'Broken',
            'source' => 'page',
            // `tag` reads the same on both sides; `confirmedAt` belongs to contacts alone.
            'triggerWhen' => [['field' => 'confirmedAt', 'op' => 'olderThan', 'value' => '7d']],
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $error = $body['error'];
        self::assertIsString($error);
        self::assertStringStartsWith('triggerWhen:', $error);
        self::assertStringContainsString('filters a contact, not a page', $error);
    }

    public function testListingAutomationsIsScopedByAudienceAndState(): void
    {
        $audience = $this->createAudience();
        $other = $this->createAudience();
        $this->createPageAutomation($audience);
        $disabled = $this->createPageAutomation($audience);
        $disabled->enabled = false;
        $this->createPageAutomation($other);
        $this->entityManager->flush();

        $body = $this->request(Request::METHOD_GET, '/api/newsletter/automation?audience='.$audience->slug);
        self::assertSame(2, $body['total']);

        $body = $this->request(Request::METHOD_GET, '/api/newsletter/automation?audience='.$audience->slug.'&enabled=0');
        self::assertSame(1, $body['total']);
    }

    public function testAnUnknownAudienceIsNotFound(): void
    {
        $this->request(Request::METHOD_GET, '/api/newsletter/automation?audience=no-such-audience');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $this->request(Request::METHOD_POST, '/api/newsletter/automation', [
            'audience' => 'no-such-audience',
            'name' => 'Orphan',
        ]);
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /** All three rules are validated, and the message says which one was wrong. */
    public function testAPageFieldInRecipientWhenIsRejected(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/automation', [
            'audience' => $audience->slug,
            'name' => 'Broken',
            'source' => 'page',
            'recipientWhen' => [['field' => 'slug', 'op' => 'startsWith', 'value' => 'blog/']],
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $error = $body['error'];
        self::assertIsString($error);
        self::assertStringStartsWith('recipientWhen:', $error);
    }

    public function testAnAutomationNeedsAName(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/automation', [
            'audience' => $audience->slug,
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame('validation', $body['error']);
    }

    public function testAPageAutomationReportsBothSidesOfItsRule(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createPageAutomation($audience, triggerWhen: [
            ['field' => 'slug', 'op' => 'startsWith', 'value' => 'nothing-matches-this/'],
        ]);

        $body = $this->request(Request::METHOD_GET, '/api/newsletter/automation/'.$automation->id);

        self::assertSame(0, $body['handled']);
        self::assertSame(0, $body['waiting']);
        self::assertSame(1, $body['matchingContacts']);
    }

    public function testDisablingAPageAutomationThroughTheApiStopsIt(): void
    {
        $audience = $this->createAudience();
        $automation = $this->createPageAutomation($audience);

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/automation/'.$automation->id, [
            'enabled' => false,
        ]);

        self::assertFalse($body['enabled']);
        self::assertSame([], $this->entityManager->getRepository(Automation::class)->findEnabled());
    }

    /** A campaign it produced is an ordinary campaign — and some of them have been sent. */
    public function testDeletingAnAutomationKeepsTheCampaignsItCreated(): void
    {
        $audience = $this->createAudience();
        $automation = $this->createPageAutomation($audience);
        $campaign = $this->createCampaign($audience);

        $this->request(Request::METHOD_DELETE, '/api/newsletter/automation/'.$automation->id);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertNotNull($this->entityManager->getRepository(Campaign::class)->find($campaign->id));
    }

    /** The occurrences a contact source produces are enrollments, and only those. */
    private function enroll(Automation $automation): int
    {
        return $this->runner()->triggerOne($automation, new DateTimeImmutable())['enrolled'];
    }

    private function runner(): AutomationRunner
    {
        return self::getContainer()->get(AutomationRunner::class);
    }

    private function userRepository(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }

    /** @param array<mixed> $body */
    private function id(array $body): int
    {
        $id = $body['id'] ?? null;
        self::assertIsInt($id);

        return $id;
    }

    private function reload(Contact $contact): Contact
    {
        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Contact::class)->find($contact->id);
        self::assertInstanceOf(Contact::class, $reloaded);

        return $reloaded;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function request(string $method, string $url, array $body = []): array
    {
        $this->client->request($method, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
            'CONTENT_TYPE' => 'application/json',
        ], [] === $body ? '' : (string) json_encode($body));

        $content = (string) $this->client->getResponse()->getContent();
        $decoded = '' === $content ? [] : json_decode($content, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
