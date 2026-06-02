<?php

namespace Pushword\PageScanner\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class PageScannerControllerTest extends AbstractAdminTestClass
{
    public function testAdmin(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/scan');
        self::assertResponseIsSuccessful();
    }

    public function testAdminWithHost(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/scan?host=localhost.dev');
        self::assertResponseIsSuccessful();
    }
}
