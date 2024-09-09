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

        $actions = ['list', 'create'];

        foreach ($actions as $action) {
            $client->request(Request::METHOD_GET, '/admin/conversation/'.$action);
            self::assertResponseIsSuccessful();
        }
    }
}
