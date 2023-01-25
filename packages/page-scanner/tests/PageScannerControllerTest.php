<?php

namespace Pushword\TemplateEditor\Tests;

use Pushword\Admin\Tests\AbstractAdminTest;

class PageScannerControllerTest extends AbstractAdminTest
{
    public function testAdmin()
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request('GET', '/admin/page/scan');
        $this->assertResponseIsSuccessful();
    }
}
