<?php

namespace Pushword\StaticGenerator\Tests;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\HttpFoundation\Request;

class StaticGeneratorControllerTest extends AbstractAdminTestClass
{
    public function testController(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/static');
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/admin/static/localhost.dev');
        self::assertResponseIsSuccessful();
    }
}
