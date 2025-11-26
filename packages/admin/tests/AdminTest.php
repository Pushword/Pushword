<?php

namespace Pushword\Admin\Tests;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminTest extends AbstractAdminTestClass
{
    public function testLogin(): void
    {
        $this->tearDown();
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/admin/');
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $client->request(Request::METHOD_GET, '/login');
        self::assertStringContainsString('Connexion', (string) $client->getResponse()->getContent());
    }

    public function testAdmins(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $routes = [
            'admin_user_list' => [],
            'admin_user_create' => [],
            'admin_user_edit' => ['id' => 1],
            'admin_media_list' => [],
            'admin_media_create' => [],
            'admin_media_edit' => ['id' => 1],
            'admin_page_list' => [],
            'admin_page_create' => [],
            'admin_page_edit' => ['id' => 1],
            'admin_page_show' => ['id' => 1],
            'admin_cheatsheet_edit' => ['id' => 1],
        ];

        foreach ($routes as $route => $parameters) {
            $client->request(Request::METHOD_GET, $this->generateAdminUrl($route, $parameters));

            if ('admin_page_show' === $route) {
                self::assertTrue($client->getResponse()->isRedirection());
                $client->followRedirect();
            }

            self::assertResponseIsSuccessful();
        }

        $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_edit', ['id' => 2]));
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/admin/cheatsheet');
        self::assertTrue($client->getResponse()->isRedirection(), (string) $client->getResponse()->getContent());
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }
}
