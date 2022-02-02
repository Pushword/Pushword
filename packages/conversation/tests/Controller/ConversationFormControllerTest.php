<?php

namespace Pushword\Conversation\Tests\Controller;

use Pushword\Conversation\Controller\ConversationFormController;
use Symfony\Component\Panther\PantherTestCase;

class ConversationFormControllerTest extends PantherTestCase
{
    public function testShowHomepage()
    {
        $client = static::createClient();

        $client->request('POST', '/conversation/message/test', [], [], ['HTTP_ORIGIN' => 'https://localhost.dev']);
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
