<?php

namespace Pushword\Newsletter\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
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

    public function testAnUnknownTokenIsNotFound(): void
    {
        $token = str_repeat('a', 64);

        $this->client->request(Request::METHOD_GET, '/newsletter/confirm/'.$token);
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $this->client->request(Request::METHOD_GET, '/newsletter/unsubscribe/'.$token);
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /** @param array<string, mixed> $parameters */
    private function post(string $audienceSlug, array $parameters): void
    {
        $this->client->request(Request::METHOD_POST, '/newsletter/subscribe', ['audience' => $audienceSlug] + $parameters);
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
}
