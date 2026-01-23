<?php

namespace Pushword\conversation\Tests\Admin;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\HttpFoundation\Request;

class ConversationAdminTest extends AbstractAdminTestClass
{
    public function testAdmin(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $actions = ['', '/new'];
        $controllers = ['conversation', 'review'];

        foreach ($controllers as $controller) {
            foreach ($actions as $action) {
                $client->request(Request::METHOD_GET, '/admin/'.$controller.$action);
                self::assertResponseIsSuccessful();
            }
        }
    }
}
