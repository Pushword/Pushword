<?php

namespace Pushword\Admin\Tests;

use Pushword\Admin\Service\AdminUrlGeneratorAlias;
use Pushword\Core\Repository\UserRepository;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

abstract class AbstractAdminTestClass extends PantherTestCase
{
    protected static bool $userCreated = false;

    protected ?KernelBrowser $client = null;

    /**
     * Override to use the absolute path of the public directory.
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $kernelOptions
     * @param array<string, mixed> $managerOptions
     */
    // @phpstan-ignore-next-line
    protected static function createPantherClient(array $options = [], array $kernelOptions = [], array $managerOptions = []): Client
    {
        if (! isset($options['webServerDir'])) {
            $publicDir = realpath(__DIR__.'/../../skeleton/public');
            if (false === $publicDir) {
                throw new RuntimeException('Public directory not found: '.__DIR__.'/../../skeleton/public');
            }

            $options['webServerDir'] = $publicDir;
        }

        // Use Chrome instead of Firefox
        if (! isset($options['browser'])) {
            $options['browser'] = static::CHROME;
        }

        // Pass critical env vars to the web server process.
        // PHPUnit's <server> directive only sets $_SERVER, not $_ENV/putenv,
        // so Panther's Process won't inherit them automatically.
        if (! isset($options['env'])) {
            $options['env'] = [];
        }

        $options['env'] = (array) $options['env'] + array_filter([
            'APP_ENV' => $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'test',
            'APP_DEBUG' => $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? '1',
            'TEST_RUN_ID' => $_SERVER['TEST_RUN_ID'] ?? $_ENV['TEST_RUN_ID'] ?? getenv('TEST_RUN_ID') ?: '',
            'PANTHER_TIMEOUT_MULTIPLIER' => $_SERVER['PANTHER_TIMEOUT_MULTIPLIER'] ?? $_ENV['PANTHER_TIMEOUT_MULTIPLIER'] ?? getenv('PANTHER_TIMEOUT_MULTIPLIER') ?: '',
        ], static fn (mixed $v): bool => '' !== $v);

        return parent::createPantherClient($options, $kernelOptions, $managerOptions);
    }

    protected function loginUser(?KernelBrowser $client = null): KernelBrowser
    {
        if (null !== $this->client) {
            return $this->client;
        }

        $client ??= static::createClient();
        $this->client = $client;

        self::createUser();

        // Step 1: Enter email
        $crawler = $client->request(Request::METHOD_GET, '/login');
        $form = $crawler->filter('[method=post]')->form();
        $form['email'] = 'admin@example.tld';
        $client->submit($form);

        // Step 2: Enter password
        $crawler = $client->followRedirect();
        $form = $crawler->filter('[method=post]')->form();
        $form['email'] = 'admin@example.tld';
        $form['password'] = 'mySecr3tpAssword';
        $client->submit($form);

        return $client;
    }

    /**
     * Generate an admin URL using the alias service so tests stay agnostic to the underlying admin stack.
     *
     * @param array<string, mixed> $parameters
     */
    protected function generateAdminUrl(string $routeName, array $parameters = []): string
    {
        /** @var AdminUrlGeneratorAlias $alias */
        $alias = static::getContainer()->get(AdminUrlGeneratorAlias::class);
        $url = $alias->generate($routeName, $parameters);
        $parsed = parse_url($url);

        if (false === $parsed) {
            return $url;
        }

        $path = $parsed['path'] ?? '/';

        if (isset($parsed['query'])) {
            $path .= '?'.$parsed['query'];
        }

        return $path;
    }

    protected static function createUser(): void
    {
        if (self::$userCreated) {
            return;
        }

        /** @var UserRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneBy(['email' => 'admin@example.tld']);

        if (null !== $testUser) {
            return;
        }

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:user:create');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'email' => 'admin@example.tld',
            'password' => 'mySecr3tpAssword',
            'role' => 'ROLE_SUPER_ADMIN',
        ]);
    }
}
