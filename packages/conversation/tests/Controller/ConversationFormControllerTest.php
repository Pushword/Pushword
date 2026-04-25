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

    public function getController(): ConversationFormController
    {
        return self::getContainer()->get(ConversationFormController::class);
    }
}
