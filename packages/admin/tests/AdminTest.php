<?php

namespace Pushword\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AdminTest extends WebTestCase
{
    private bool $userCreated = false;

    public function testLogin()
    {
        $client = static::createClient();

        $client->request('GET', '/admin/');
        $this->assertEquals(301, $client->getResponse()->getStatusCode());

        $client->request('GET', '/login');
        $this->assertStringContainsString('Connexion', $client->getResponse());
    }

    public function loginUser(): KernelBrowser
    {
        if (false === $this->userCreated) {
            $this->createUser();
        }

        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('[method=post]')->form();
        $form['email'] = 'admin@example.tld';
        $form['password'] = 'mySecr3tpAssword';
        $crawler = $client->submit($form);

        return $client;
    }

    public function testAdmins()
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $actions = ['list', 'create'];
        $admins = ['user', 'media', 'page'];

        foreach ($admins as $admin) {
            foreach ($actions as $action) {
                $client->request('GET', '/admin/app/'.$admin.'/'.$action);
                $this->assertResponseIsSuccessful();
            }
        }
    }

    private function createUser()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pushword:user:create');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'email' => 'admin@example.tld',
            'password' => 'mySecr3tpAssword',
            'role' => 'ROLE_SUPER_ADMIN',
        ]);
    }
}
