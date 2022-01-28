<?php

namespace Pushword\Conversation\Tests\Controller;

use Pushword\Conversation\Controller\ConversationFormController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class ConversationFormControllerTest extends KernelTestCase
{
    public function testShowHomepage()
    {
        $request = Request::create('/testit?referring=test&type=ms-message');
        $request->headers->set('origin', 'https://localhost.dev');
        $response = $this->getController()->show($request, 'message', 'localhost.dev');
        $this->assertSame(200, $response->getStatusCode());
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
