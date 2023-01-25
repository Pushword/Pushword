<?php

namespace Pushword\Conversation\Tests\Controller;

use Pushword\Conversation\Controller\ConversationFormController;
use Symfony\Component\Panther\PantherTestCase;

class ConversationFormControllerTest extends PantherTestCase
{
    public function testMessageForm()
    {
        $client = static::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $crawler = $client->request('POST', '/conversation/message/test', [], [], $server);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $form = $crawler->filter('[name="form"]')->form([
            'form[content]' => 'Ceci est un message de test',
        ]);
        $client->catchExceptions(false);
        $client->submit($form, [], $server);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * @return ConversationFormController
     */
    public function getController()
    {
        return $this->getService(ConversationFormController::class);
    }

    public function getService(string $service)
    {
        self::bootKernel();
        $container = static::getContainer();

        return $container->get($service);
    }
}
