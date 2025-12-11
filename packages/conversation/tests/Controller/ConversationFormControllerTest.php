<?php

namespace Pushword\Conversation\Tests\Controller;

use Pushword\Conversation\Controller\ConversationFormController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConversationFormControllerTest extends WebTestCase
{
    public function testNewsletterForm(): void
    {
        $client = static::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $crawler = $client->request(Request::METHOD_GET, '/conversation/newsletter/test', [], [], $server);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertStringContainsString('pattern="[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{1,63}$"', (string) $client->getResponse()->getContent());
    }

    public function testMessageForm(): void
    {
        $client = static::createClient();

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

    public function getController(): ConversationFormController
    {
        return static::getContainer()->get(ConversationFormController::class);
    }
}
