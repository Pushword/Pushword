<?php

namespace Pushword\Conversation\Tests\Controller;

use Pushword\Conversation\Controller\ConversationFormController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class ConversationFormControllerTest extends KernelTestCase
{
    public function testShowHomepage()
    {
        $request = Request::create('/testit?referring=test');
        $request->headers->set('origin', 'https://localhost.dev');
        $response = $this->getController()->show('message', 'localhost.dev', $request);
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

        return self::$kernel->getContainer()->get($service);
    }
}
