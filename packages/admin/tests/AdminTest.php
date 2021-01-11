<?php

namespace Pushword\Admin\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AdminTest extends AbstractAdminTest
{
    public function testLogin()
    {
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
    }
}
