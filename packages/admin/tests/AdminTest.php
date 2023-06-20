<?php

namespace Pushword\Admin\Tests;

class AdminTest extends AbstractAdminTestClass
{
    public function testLogin()
    {
        $this->tearDown();
        $client = static::createClient();

        $client->request('GET', '/admin/');
        $this->assertEquals(301, $client->getResponse()->getStatusCode());

        $client->request('GET', '/login');
        $this->assertStringContainsString('Connexion', $client->getResponse());
    }

    public function testAdmins()
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $actions = ['list', 'create', '1/edit'];
        $admins = ['user', 'media', 'page'];

        foreach ($admins as $admin) {
            foreach ($actions as $action) {
                $client->request('GET', '/admin/app/'.$admin.'/'.$action);
                $this->assertResponseIsSuccessful();
            }
        }

        $client->request('GET', '/admin/app/page/2/edit');
        $this->assertResponseIsSuccessful();
    }
}
