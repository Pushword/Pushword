<?php

namespace Pushword\Conversation\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Controller\ConversationFormController;
use Pushword\Conversation\Entity\Message;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class ConversationFormControllerTest extends WebTestCase
{
    public function testNewsletterForm(): void
    {
        $client = self::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $client->request(Request::METHOD_GET, '/conversation/newsletter/test', [], [], $server);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertStringContainsString('pattern="[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{1,63}$"', (string) $client->getResponse()->getContent());
    }

    public function testMessageForm(): void
    {
        $client = self::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $crawler = $client->request(Request::METHOD_POST, '/conversation/message/test', [], [], $server);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $form = $crawler->filter('[name="form"]')->form([
            'form[content]' => 'Ceci est un message de test',
        ]);
        $client->catchExceptions(false);
        $client->submit($form, [], $server);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testMessageFormDeduplication(): void
    {
        $client = self::createClient();
        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $uniqueContent = 'Dedup test message '.uniqid();

        // First submit
        $crawler = $client->request(Request::METHOD_POST, '/conversation/message/test', [], [], $server);
        $form = $crawler->filter('[name="form"]')->form([
            'form[content]' => $uniqueContent,
            'form[authorEmail]' => 'dedup@example.com',
            'form[authorName]' => 'Test',
        ]);
        $client->catchExceptions(false);
        $client->submit($form, [], $server);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Thank you', (string) $client->getResponse()->getContent());

        // Second submit with same content+email — should be deduplicated
        $crawler = $client->request(Request::METHOD_POST, '/conversation/message/test', [], [], $server);
        $form = $crawler->filter('[name="form"]')->form([
            'form[content]' => $uniqueContent,
            'form[authorEmail]' => 'dedup@example.com',
            'form[authorName]' => 'Test',
        ]);
        $client->submit($form, [], $server);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Thank you', (string) $client->getResponse()->getContent());

        // Verify only one message was persisted
        $em = self::getContainer()->get('doctrine')->getManager();
        $messages = $em->getRepository(Message::class)
            ->findBy(['content' => $uniqueContent]);
        self::assertCount(1, $messages, 'Duplicate message should not be persisted');
    }

    public function testNewsletterFormWithQueryParams(): void
    {
        $client = self::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/test?locale=fr&host=localhost.dev',
            [],
            [],
            $server,
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testFormWithoutOriginHeader(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/conversation/newsletter/test');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertNull($client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testConversationWithSlashInReferring(): void
    {
        $client = self::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/some/path/with/slashes?locale=en&host=localhost.dev',
            [],
            [],
            $server,
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    /**
     * Regression test for a worker-mode state leak: this controller is a shared
     * service, so under a long-running worker (FrankenPHP, RoadRunner…) the same
     * instance handles successive requests. It used to cache $form / $possibleOrigins
     * across requests, pinning every later request to the first one's site — so a
     * second request from a different host saw the first host's allowed origins and
     * was rejected with "origin sent is not authorized". disableReboot() reuses the
     * kernel between requests to reproduce that worker reuse.
     */
    public function testStateDoesNotLeakAcrossRequestsWhenKernelIsReused(): void
    {
        $client = self::createClient();
        $client->disableReboot();

        // Request #1 resolves the localhost.dev site and its allowed origins.
        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/test?host=localhost.dev',
            [],
            [],
            ['HTTP_ORIGIN' => 'https://localhost.dev'],
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertSame('https://localhost.dev', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));

        // Request #2 targets a different site whose origin is only valid once the
        // per-request state is rebuilt. Without the reset it 500s on the cached origins.
        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/test?host=pushword.piedweb.com',
            [],
            [],
            ['HTTP_ORIGIN' => 'https://pushword.piedweb.com'],
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertSame('https://pushword.piedweb.com', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function getController(): ConversationFormController
    {
        return self::getContainer()->get(ConversationFormController::class);
    }
}
