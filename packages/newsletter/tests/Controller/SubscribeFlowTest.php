<?php

namespace Pushword\Newsletter\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;

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
        self::assertStringContainsString('/newsletter/confirm/'.$contact->getToken(), (string) $email->getTextBody());
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
        ]);

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->repository()->findOneByEmail($known, 'partial@example.tld'));
    }

    public function testUntickingEverythingAsksAgainRatherThanSubscribing(): void
    {
        $this->createAudience(requireDoubleOptIn: false);

        $this->client->request(Request::METHOD_POST, '/newsletter/subscribe', ['email' => 'nothing@example.tld']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            $this->translate('newsletter.subscribe.noAudience'),
            (string) $this->client->getResponse()->getContent(),
        );
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
        $this->post('does-not-exist', ['email' => 'nobody@example.tld']);

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

    /** A mail scanner following the link must not opt anyone out. */
    public function testUnsubscribeNeedsThePost(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $token = $this->createContact($audience, 'leaving@example.tld')->getToken();

        $this->client->request(Request::METHOD_GET, '/newsletter/unsubscribe/'.$token);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('<form method="post"', (string) $this->client->getResponse()->getContent());
        self::assertSame(ContactStatus::Subscribed, $this->find('leaving@example.tld')->getStatus());

        $this->client->request(Request::METHOD_POST, '/newsletter/unsubscribe/'.$token);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $left = $this->find('leaving@example.tld');
        self::assertSame(ContactStatus::Unsubscribed, $left->getStatus());
        self::assertNotNull($left->getUnsubscribedAt());
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
        ]);

        self::assertEmailCount(1);
        self::assertCount(1, $this->entityManager->getRepository(Contact::class)->findBy(['email' => 'twice@example.tld']));
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
    }

    /** @param array<string, mixed> $parameters */
    private function post(string $audienceSlug, array $parameters): void
    {
        $this->client->request(Request::METHOD_POST, '/newsletter/subscribe', ['audience' => $audienceSlug] + $parameters);
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

    private function translate(string $key): string
    {
        return self::getContainer()->get('translator')->trans($key);
    }
}
