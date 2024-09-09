<?php

namespace Pushword\TemplateEditor\Tests;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\HttpFoundation\Request;

class PageScannerControllerTest extends AbstractAdminTestClass
{
    public function testAdmin(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/page/scan');
        self::assertResponseIsSuccessful();
    }
}
