<?php

namespace Pushword\conversation\Tests\Admin;

use Pushword\Admin\Tests\AbstractAdminTest;

class ConversationAdminTest extends AbstractAdminTest
{
    public function testAdmins()
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $actions = ['list', 'create'];

        foreach ($actions as $action) {
            $client->request('GET', '/admin/pushword/conversation/message/'.$action);
            $this->assertResponseIsSuccessful();
        }
    }
}
