<?php

namespace Pushword\AdminBlockEditor\Tests;

use Pushword\Admin\Tests\AbstractAdminTest;

class ControllerTest extends AbstractAdminTest
{
    public function testIt()
    {
        $client = $this->loginUser();

        $client->request('GET', 'admin/app/page/1/edit');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        // poor test
    }
}
