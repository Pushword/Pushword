<?php

namespace Pushword\Admin\Tests;

use Pushword\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Panther\PantherTestCase;

abstract class AbstractAdminTestClass extends PantherTestCase
{
    protected static bool $userCreated = false;

    protected $client;

    protected function loginUser($client = null): KernelBrowser
    {
        if (null !== $this->client) {
            return $this->client;
        }

        $this->client = $client ?: static::createClient();

        self::createUser();

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('[method=post]')->form();
        $form['email'] = 'admin@example.tld';
        $form['password'] = 'mySecr3tpAssword';
        $crawler = $this->client->submit($form);

        return $this->client;
    }

    protected static function createUser(): void
    {
        if (true === self::$userCreated) {
            return;
        }

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@example.tld');

        if ($testUser) {
            return;
        }

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
