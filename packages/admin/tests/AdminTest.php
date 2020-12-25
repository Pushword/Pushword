<?php

namespace Pushword\Admin\Tests;

use Symfony\Component\HttpFoundation\Response;

class AdminTest extends AbstractAdminTestClass
{
    public function testLogin(): void
    {
        $this->tearDown();
        $client = static::createClient();

        $client->request('GET', '/admin/');
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $client->request('GET', '/login');
        self::assertStringContainsString('Connexion', $client->getResponse());
    }

    public function testAdmins(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $actions = ['list', 'create', '1/edit'];
        $admins = ['user', 'media', 'page'];

        foreach ($admins as $admin) {
            foreach ($actions as $action) {
                $client->request('GET', '/admin/'.$admin.'/'.$action);
                self::assertResponseIsSuccessful();
            }
        }

        $client->request('GET', '/admin/page/2/edit');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/cheatsheet');
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }
}
