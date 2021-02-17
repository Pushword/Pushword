<?php

namespace Pushword\AdminBlockEditor\Tests;

use Pushword\Admin\Tests\AbstractAdminTest;

class ControllerTest extends AbstractAdminTest
{
    public function testIt()
    {
        $client = $this->loginUser(
            //static::createPantherClient([            'webServerDir' => __DIR__.'/../../skeleton/public'        ])
        );

        $client->request('GET', '/admin/app/page/create');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        // poor test

        //$client->waitFor('.ce-toolbar__plus');
    }
}
