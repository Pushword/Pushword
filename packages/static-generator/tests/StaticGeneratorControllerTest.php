<?php

namespace Pushword\StaticGenerator\Tests;

use Pushword\Admin\Tests\AbstractAdminTestClass;

class StaticGeneratorControllerTest extends AbstractAdminTestClass
{
    public function testController(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request('GET', '/admin/static');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/static/localhost.dev');
        self::assertResponseIsSuccessful();
    }
}
