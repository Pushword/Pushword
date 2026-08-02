<?php

namespace Pushword\Newsletter\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

#[Group('integration')]
final class SubscribeFlowTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        // The per-IP ceiling is real state in the shared cache pool; every test
        // in this class posts from 127.0.0.1.
        self::getContainer()->get('cache.app')->deleteItem('pushword_newsletter_subscribe_'.md5('127.0.0.1'));
    }

    public function testSubscribingCreatesAPendingContactAndMailsTheConfirmation(): void
    {
        $audience = $this->createAudience();

        $this->post($audience->getSlug(), ['email' => 'new@example.tld', 'name' => 'Robin']);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $contact = $this->find('new@example.tld');
        self::assertSame(ContactStatus::Pending, $contact->getStatus());
        self::assertSame('Robin', $contact->getName());
        self::assertSame('localhost', $contact->getOptinHost());

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        $text = (string) $email->getTextBody();
        self::assertStringContainsString('/newsletter/confirm/'.$contact->getToken(), $text);
        self::assertStringContainsString($this->translate('newsletter.confirm.body', 'en'), $text, 'the text part reads like a mail, not a bare link');
        self::assertStringContainsString($this->translate('newsletter.confirm.ignore', 'en'), $text);
    }

    /**
     * A transport refusing the mailbox — a typo in the address, mostly — must
     * answer the visitor rather than crash: no 500, and no pending contact a
     * confirmation never reached.
     */
    public function testARefusedRecipientGetsAnAnswerAndLeavesNothingBehind(): void
    {
        $audience = $this->createAudience();
        self::getContainer()->set('mailer.mailer', new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new TransportException('550 5.1.1 Recipient address rejected');
            }
        });

        $this->post($audience->getSlug(), ['email' => 'typo@rejected.tld']);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            $this->translate('newsletter.subscribe.sendFailed'),
            (string) $this->client->getResponse()->getContent(),
        );
        self::assertNull($this->repository()->findOneByEmail($audience, 'typo@rejected.tld'));
    }

    /** The alert answers in the locale the form sent, not the live host's. */
    public function testTheAlertAndTheMailSpeakTheLocaleTheFormSent(): void
    {
        $audience = $this->createAudience();

        $this->post($audience->getSlug(), ['email' => 'francois@example.tld', 'locale' => 'fr']);

        self::assertStringContainsString(
            $this->translate('newsletter.subscribe.pending', 'fr'),
            (string) $this->client->getResponse()->getContent(),
        );
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertStringContainsString($this->translate('newsletter.confirm.body', 'fr'), (string) $email->getTextBody());
    }

    public function testConfirmingSubscribes(): void
    {
        $audience = $this->createAudience();
        $this->post($audience->getSlug(), ['email' => 'confirm@example.tld']);
        $contact = $this->find('confirm@example.tld');

        $this->client->request(Request::METHOD_GET, '/newsletter/confirm/'.$contact->getToken());

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $confirmed = $this->find('confirm@example.tld');
        self::assertSame(ContactStatus::Subscribed, $confirmed->getStatus());
        self::assertNotNull($confirmed->getConfirmedAt());
    }

    public function testAnAudienceWithoutDoubleOptInSubscribesImmediately(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);

        $this->post($audience->getSlug(), ['email' => 'direct@example.tld']);

        self::assertSame(ContactStatus::Subscribed, $this->find('direct@example.tld')->getStatus());
        self::assertEmailCount(0);
    }

    public function testResubmittingWhilePendingResendsTheConfirmation(): void
    {
        $audience = $this->createAudience();
        $this->post($audience->getSlug(), ['email' => 'again@example.tld']);
        self::assertEmailCount(1);

        $this->post($audience->getSlug(), ['email' => 'again@example.tld']);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertEmailCount(1, null, 'a pending contact asking again gets the link again');
        self::assertCount(1, $this->entityManager->getRepository(Contact::class)->findBy(['email' => 'again@example.tld']));
    }

    /** An address already on the list must not receive a fresh confirmation ask. */
    public function testResubmittingWhenAlreadySubscribedSendsNothing(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $this->post($audience->getSlug(), ['email' => 'known@example.tld']);

        $this->post($audience->getSlug(), ['email' => 'known@example.tld', 'name' => 'Updated']);

        self::assertEmailCount(0);
        self::assertSame('Updated', $this->find('known@example.tld')->getName());
    }

    public function testOnlyDeclaredInterestsAreAttached(): void
    {
        $audience = $this->createAudience(interests: ['AmTrek', 'AmBivouac']);

        $this->post($audience->getSlug(), [
            'email' => 'tagged@example.tld',
            'interests' => ['AmTrek', 'NotDeclared'],
        ]);

        self::assertSame(['AmTrek'], $this->find('tagged@example.tld')->getTagList());
    }

    public function testOneSubmissionCanOpenSeveralSubscriptions(): void
    {
        $letter = $this->createAudience(requireDoubleOptIn: false);
        $promos = $this->createAudience(requireDoubleOptIn: false);
        $untouched = $this->createAudience(requireDoubleOptIn: false);

        $this->client->request(Request::METHOD_POST, '/newsletter/subscribe', [
            'audiences' => [$letter->getSlug(), $promos->getSlug()],
            'email' => 'several@example.tld',
            '_token' => $this->csrfToken($letter->getSlug()),
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(ContactStatus::Subscribed, $this->findIn($letter, 'several@example.tld')->getStatus());
        self::assertSame(ContactStatus::Subscribed, $this->findIn($promos, 'several@example.tld')->getStatus());
        self::assertNull($this->repository()->findOneByEmail($untouched, 'several@example.tld'), 'an unticked list stays unticked');
    }

    /** Each list confirms for itself — and one link left to click is what the page must say. */
    public function testEachListNeedingAConfirmationAsksForItsOwn(): void
    {
        $direct = $this->createAudience(requireDoubleOptIn: false);
        $confirmed = $this->createAudience();

        $this->client->request(Request::METHOD_POST, '/newsletter/subscribe', [
            'audiences' => [$direct->getSlug(), $confirmed->getSlug()],
            'email' => 'mixed@example.tld',
            '_token' => $this->csrfToken($direct->getSlug()),
        ]);

        self::assertEmailCount(1);
        self::assertStringContainsString(
            $this->translate('newsletter.subscribe.pending'),
            (string) $this->client->getResponse()->getContent(),
        );
        self::assertSame(ContactStatus::Subscribed, $this->findIn($direct, 'mixed@example.tld')->getStatus());
        self::assertSame(ContactStatus::Pending, $this->findIn($confirmed, 'mixed@example.tld')->getStatus());
    }

    /** Half a subscription is not what anyone ticked. */
    public function testAnUnknownSlugInTheListFailsTheWholeSubmission(): void
    {
        $known = $this->createAudience(requireDoubleOptIn: false);

        $this->client->request(Request::METHOD_POST, '/newsletter/subscribe', [
            'audiences' => [$known->getSlug(), 'does-not-exist'],
            'email' => 'partial@example.tld',
            '_token' => $this->csrfToken($known->getSlug()),
        ]);

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->repository()->findOneByEmail($known, 'partial@example.tld'));
    }

    public function testUntickingEverythingAsksAgainRatherThanSubscribing(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);

        $this->client->request(Request::METHOD_POST, '/newsletter/subscribe', [
            'email' => 'nothing@example.tld',
            '_token' => $this->csrfToken($audience->getSlug()),
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            $this->translate('newsletter.subscribe.noAudience'),
            (string) $this->client->getResponse()->getContent(),
        );
    }

    /**
     * The alert is the whole response body, so its `context` is the only thing
     * telling a reader success from failure. Both branches are spelled out as
     * complete class strings in the template — a class built by concatenation
     * would never be emitted by Tailwind — so both are asserted here.
     */
    public function testTheAlertLooksDifferentWhenItSucceededAndWhenItFailed(): void
    {
        $audience = $this->createAudience();

        $this->post($audience->getSlug(), ['email' => 'reader@example.tld']);
        $success = (string) $this->client->getResponse()->getContent();

        $this->post($audience->getSlug(), ['email' => 'not-an-email']);
        $error = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('bg-green-100', $success);
        self::assertStringNotContainsString('bg-red-100', $success);
        self::assertStringContainsString('bg-red-100', $error);
        self::assertStringNotContainsString('bg-green-100', $error);
    }

    public function testAFilledHoneypotLooksLikeSuccessAndWritesNothing(): void
    {
        $audience = $this->createAudience();

        $this->post($audience->getSlug(), ['email' => 'bot@example.tld', 'website' => 'http://spam.example']);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->repository()->findOneByEmail($audience, 'bot@example.tld'));
        self::assertEmailCount(0);
    }

    public function testAnInvalidEmailIsRejected(): void
    {
        $audience = $this->createAudience();

        $this->post($audience->getSlug(), ['email' => 'not-an-email']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        self::assertEmailCount(0);
    }

    public function testAnUnknownAudienceIsNotFound(): void
    {
        $audience = $this->createAudience();

        $this->post('does-not-exist', ['email' => 'nobody@example.tld'], tokenFrom: $audience->getSlug());

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testTheRateLimitStopsBulkSubmissions(): void
    {
        $audience = $this->createAudience();

        for ($i = 0; $i < 10; ++$i) {
            $this->post($audience->getSlug(), ['email' => 'flood'.$i.'@example.tld']);
            self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        }

        $this->post($audience->getSlug(), ['email' => 'flood10@example.tld']);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->repository()->findOneByEmail($audience, 'flood10@example.tld'));
    }

    /** Clicking the link in the mail is the whole opt-out: nothing left to confirm. */
    public function testAClickUnsubscribesOnTheSpot(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $token = $this->createContact($audience, 'leaving@example.tld')->getToken();

        $this->click('/newsletter/unsubscribe/'.$token);

        self::assertStringContainsString(
            $this->translate('newsletter.unsubscribed.title'),
            (string) $this->client->getResponse()->getContent(),
        );

        $left = $this->find('leaving@example.tld');
        self::assertSame(ContactStatus::Unsubscribed, $left->getStatus());
        self::assertNotNull($left->getUnsubscribedAt());
    }

    /**
     * A fetch reports no gesture, and opts nobody out — whether it asks plainly
     * or sets the headers a navigation carries.
     *
     * @param array<string, string> $headers
     */
    #[DataProvider('gesturelessFetchProvider')]
    public function testAFetchWithoutAGestureOnlyGetsThePage(array $headers): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $token = $this->createContact($audience, 'scanned@example.tld')->getToken();

        $this->client->request(Request::METHOD_GET, '/newsletter/unsubscribe/'.$token, server: $headers);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('<form method="post"', (string) $this->client->getResponse()->getContent());
        self::assertSame(ContactStatus::Subscribed, $this->find('scanned@example.tld')->getStatus());

        $this->client->request(Request::METHOD_POST, '/newsletter/unsubscribe/'.$token);

        self::assertSame(ContactStatus::Unsubscribed, $this->find('scanned@example.tld')->getStatus());
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function gesturelessFetchProvider(): iterable
    {
        yield 'a plain fetch' => [[]];

        // Not a real browser — Chromium sends all four of these, `Sec-Fetch-User`
        // included, even for a scripted navigation. This pins which header the
        // guard reads, so nobody widens it to "any Sec-Fetch header will do".
        yield 'a fetch dressed as a navigation' => [[
            'HTTP_SEC_FETCH_SITE' => 'none',
            'HTTP_SEC_FETCH_MODE' => 'navigate',
            'HTTP_SEC_FETCH_DEST' => 'document',
        ]];
    }

    /**
     * Leaving costs one click, so coming back costs one too: the token reached
     * them by mail, which is the proof a confirmation would ask for again.
     */
    public function testTheUndoPutsThemBackWithNoConfirmationMail(): void
    {
        $audience = $this->createAudience();
        $token = $this->createContact($audience, 'oops@example.tld')->getToken();

        $this->click('/newsletter/unsubscribe/'.$token);
        self::assertStringContainsString(
            $this->translate('newsletter.unsubscribed.undo'),
            (string) $this->client->getResponse()->getContent(),
        );

        $this->client->request(Request::METHOD_POST, '/newsletter/unsubscribe/'.$token.'/undo');

        self::assertStringContainsString(
            $this->translate('newsletter.resubscribed.title'),
            (string) $this->client->getResponse()->getContent(),
        );

        $back = $this->find('oops@example.tld');
        self::assertSame(ContactStatus::Subscribed, $back->getStatus());
        self::assertNull($back->getUnsubscribedAt());
        self::assertEmailCount(0);
    }

    /**
     * A token exists from the moment somebody subscribes, so a contact still
     * waiting on their double opt-in holds one. It must not be a way around the
     * confirmation they were sent: undoing an opt-out they never made would
     * promote them without anyone answering that mail.
     */
    public function testTheUndoCannotConfirmAContactStillPending(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'waiting@example.tld', subscribed: false);

        $this->client->request(Request::METHOD_POST, '/newsletter/unsubscribe/'.$contact->getToken().'/undo');

        self::assertSame(ContactStatus::Pending, $this->find('waiting@example.tld')->getStatus());
    }

    /** Once gone, there is nothing left to confirm — and the other lists stay one click away. */
    public function testAFetchOfAnAlreadyLeftAddressStillGetsTheOtherLists(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $sibling = $this->createAudience(requireDoubleOptIn: false);
        $token = $this->createContact($audience, 'back@example.tld')->getToken();
        $this->createContact($sibling, 'back@example.tld');

        $this->click('/newsletter/unsubscribe/'.$token);
        $this->client->request(Request::METHOD_GET, '/newsletter/unsubscribe/'.$token);

        self::assertStringContainsString('value="'.$sibling->getSlug().'"', (string) $this->client->getResponse()->getContent());
    }

    /**
     * Leaving one list must not leave the others — the one-click POST comes from
     * the mailbox provider and speaks for one audience only. The others are
     * offered on the page, and only those sharing the audience's host.
     */
    public function testUnsubscribingOffersTheOtherListsOfTheSameHostWithoutTouchingThem(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $sameHost = $this->createAudience(requireDoubleOptIn: false);
        $otherHost = $this->createAudience(requireDoubleOptIn: false, mainHost: 'elsewhere.dev');
        $notConfirmed = $this->createAudience();

        $token = $this->createContact($audience, 'multi@example.tld')->getToken();
        $this->createContact($sameHost, 'multi@example.tld');
        $this->createContact($otherHost, 'multi@example.tld');
        $this->createContact($notConfirmed, 'multi@example.tld', subscribed: false);

        $this->client->request(Request::METHOD_POST, '/newsletter/unsubscribe/'.$token);

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('value="'.$sameHost->getSlug().'"', $html);
        self::assertStringNotContainsString($otherHost->getSlug(), $html, 'another host is another brand');
        self::assertStringNotContainsString($notConfirmed->getSlug(), $html, 'a pending contact is on no list yet');
        self::assertSame(ContactStatus::Subscribed, $this->findIn($sameHost, 'multi@example.tld')->getStatus());
    }

    public function testLeavingTheOtherListsActsOnlyOnWhatWasTicked(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $ticked = $this->createAudience(requireDoubleOptIn: false);
        $untouched = $this->createAudience(requireDoubleOptIn: false);

        $token = $this->createContact($audience, 'picky@example.tld')->getToken();
        $this->createContact($ticked, 'picky@example.tld');
        $this->createContact($untouched, 'picky@example.tld');

        $this->client->request(
            Request::METHOD_POST,
            '/newsletter/unsubscribe/'.$token.'/others',
            ['audiences' => [$ticked->getSlug()]],
        );

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(ContactStatus::Unsubscribed, $this->findIn($ticked, 'picky@example.tld')->getStatus());
        self::assertSame(ContactStatus::Subscribed, $this->findIn($untouched, 'picky@example.tld')->getStatus());
    }

    public function testLeavingEverythingStopsEveryListOfTheHost(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $first = $this->createAudience(requireDoubleOptIn: false);
        $second = $this->createAudience(requireDoubleOptIn: false);
        $elsewhere = $this->createAudience(requireDoubleOptIn: false, mainHost: 'elsewhere.dev');

        $token = $this->createContact($audience, 'gone@example.tld')->getToken();
        $this->createContact($first, 'gone@example.tld');
        $this->createContact($second, 'gone@example.tld');
        $this->createContact($elsewhere, 'gone@example.tld');

        $this->client->request(Request::METHOD_POST, '/newsletter/unsubscribe/'.$token.'/others', ['all' => '1']);

        self::assertSame(ContactStatus::Unsubscribed, $this->findIn($first, 'gone@example.tld')->getStatus());
        self::assertSame(ContactStatus::Unsubscribed, $this->findIn($second, 'gone@example.tld')->getStatus());
        self::assertSame(ContactStatus::Subscribed, $this->findIn($elsewhere, 'gone@example.tld')->getStatus());
    }

    /**
     * The submitted slugs pick from what the token may touch, they never widen
     * it: a list of another host stays out of reach even when asked for by name.
     */
    public function testAForeignSlugIsIgnoredEvenWhenSubmitted(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $elsewhere = $this->createAudience(requireDoubleOptIn: false, mainHost: 'elsewhere.dev');

        $token = $this->createContact($audience, 'reaching@example.tld')->getToken();
        $this->createContact($elsewhere, 'reaching@example.tld');

        $this->client->request(
            Request::METHOD_POST,
            '/newsletter/unsubscribe/'.$token.'/others',
            ['audiences' => [$elsewhere->getSlug()]],
        );

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(ContactStatus::Subscribed, $this->findIn($elsewhere, 'reaching@example.tld')->getStatus());
    }

    /** The same list ticked twice is one subscription, hence one confirmation to click. */
    public function testTheSameListSubmittedTwiceSubscribesOnce(): void
    {
        $audience = $this->createAudience();

        $this->client->request(Request::METHOD_POST, '/newsletter/subscribe', [
            'audiences' => [$audience->getSlug(), $audience->getSlug()],
            'email' => 'twice@example.tld',
            '_token' => $this->csrfToken($audience->getSlug()),
        ]);

        self::assertEmailCount(1);
        self::assertCount(1, $this->entityManager->getRepository(Contact::class)->findBy(['email' => 'twice@example.tld']));
    }

    /**
     * The host serving these pages is the audience's, the only one its links are
     * built from — a French list reached from an English site would otherwise
     * answer in French to everyone.
     */
    public function testThesePagesSpeakTheContactsLocaleAndNotTheHosts(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'francois@example.tld', subscribed: false, locale: 'fr');

        $this->client->request(Request::METHOD_GET, '/newsletter/confirm/'.$contact->getToken());

        self::assertStringContainsString(
            $this->translate('newsletter.confirmed.title', 'fr'),
            (string) $this->client->getResponse()->getContent(),
        );

        $this->click('/newsletter/unsubscribe/'.$contact->getToken());

        self::assertStringContainsString(
            $this->translate('newsletter.unsubscribed.title', 'fr'),
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testAnUnknownTokenIsNotFound(): void
    {
        $token = str_repeat('a', 64);

        $this->client->request(Request::METHOD_GET, '/newsletter/confirm/'.$token);
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $this->client->request(Request::METHOD_GET, '/newsletter/unsubscribe/'.$token);
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $this->client->request(Request::METHOD_POST, '/newsletter/unsubscribe/'.$token.'/others');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $this->client->request(Request::METHOD_POST, '/newsletter/unsubscribe/'.$token.'/undo');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /** A navigation a browser attributes to a real gesture, which is what a click is. */
    private function click(string $uri): void
    {
        $this->client->request(Request::METHOD_GET, $uri, server: ['HTTP_SEC_FETCH_USER' => '?1']);
    }

    /** @param array<string, mixed> $parameters */
    private function post(string $audienceSlug, array $parameters, ?string $tokenFrom = null): void
    {
        // A rejected slug still has to carry a token, so the list under test and
        // the list that opens the session are named apart.
        $token = $this->csrfToken($tokenFrom ?? $audienceSlug);

        $this->client->request(Request::METHOD_POST, '/newsletter/subscribe', ['audience' => $audienceSlug, '_token' => $token] + $parameters);
    }

    private function findIn(Audience $audience, string $email): Contact
    {
        $contact = $this->repository()->findOneByEmail($audience, $email);
        self::assertInstanceOf(Contact::class, $contact);

        return $contact;
    }

    private function find(string $email): Contact
    {
        $contact = $this->entityManager->getRepository(Contact::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(Contact::class, $contact);

        return $contact;
    }

    private function repository(): ContactRepository
    {
        return self::getContainer()->get(ContactRepository::class);
    }

    private function translate(string $key, ?string $locale = null): string
    {
        return self::getContainer()->get('translator')->trans($key, [], null, $locale);
    }
}
