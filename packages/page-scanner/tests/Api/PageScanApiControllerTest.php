<?php

namespace Pushword\PageScanner\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Core\Service\ProcessOutputStorage;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class PageScanApiControllerTest extends WebTestCase
{
    private const string HOST = 'localhost.dev';

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'page-scan-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $this->em->persist($user);
        $this->em->flush();

        $this->cleanScanState();
    }

    protected function tearDown(): void
    {
        $this->cleanScanState();

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = $this->em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $this->em->remove($user);
            $this->em->flush();
        }

        parent::tearDown();
    }

    public function testStatusRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/page-scan');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testUnknownHostIsRejected(): void
    {
        $this->request('GET', '/api/page-scan?host=does-not-exist.invalid');

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testStatusIsIdleWhenNeverScanned(): void
    {
        $body = $this->request('GET', '/api/page-scan?host='.self::HOST);

        self::assertResponseIsSuccessful();
        self::assertSame('idle', $body['status']);
        self::assertFalse($body['running']);
        self::assertNull($body['lastScannedAt']);
        self::assertSame(0, $body['errorCount']);
        self::assertSame([], $body['errors']);
    }

    public function testStatusReturnsCachedFindings(): void
    {
        $this->seedResults([7 => [
            ['page' => ['host' => self::HOST, 'slug' => 'broken'], 'message' => '404 <a href="/missing">/missing</a>'],
        ]]);

        $body = $this->request('GET', '/api/page-scan?host='.self::HOST);

        self::assertResponseIsSuccessful();
        self::assertSame('completed', $body['status']);
        self::assertNotNull($body['lastScannedAt']);
        self::assertSame(1, $body['errorCount']);

        self::assertIsArray($body['errors']);
        $first = $body['errors'][0];
        self::assertIsArray($first);
        self::assertSame(self::HOST, $first['host']);
        self::assertSame('broken', $first['slug']);
        // HTML stripped to a plain, agent-friendly message.
        self::assertSame('404 /missing', $first['message']);
    }

    public function testStatusForAllHostsWhenNeverScanned(): void
    {
        $body = $this->request('GET', '/api/page-scan');

        self::assertResponseIsSuccessful();
        self::assertNull($body['host']);
        self::assertSame('idle', $body['status']);
        self::assertSame(0, $body['errorCount']);
    }

    public function testErrorStatusExposesConsoleOutput(): void
    {
        $this->storage()->setStatus('page-scanner--'.self::HOST, 'error');
        $this->storage()->write('page-scanner--'.self::HOST, 'Failed to start background process: nohup failed');

        $body = $this->request('GET', '/api/page-scan?host='.self::HOST);

        self::assertResponseIsSuccessful();
        self::assertSame('error', $body['status']);
        self::assertArrayHasKey('output', $body);
        self::assertIsString($body['output']);
        self::assertStringContainsString('nohup failed', $body['output']);
    }

    public function testTriggerWithFreshCacheDoesNotStartANewScan(): void
    {
        $this->seedResults([]);

        $body = $this->request('POST', '/api/page-scan?host='.self::HOST);

        // A fresh cache within the min-interval window is returned as-is; no scan starts.
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertFalse($body['started']);
        self::assertSame('completed', $body['status']);
        self::assertSame(0, $body['errorCount']);
    }

    private function varDir(): string
    {
        // The same per-worker runtime dir the coordinator and output storage use,
        // so seeded state and the controller's reads never diverge (or collide
        // with a parallel worker).
        return self::getContainer()->getParameter('pw.var_dir');
    }

    /**
     * @param array<int, array<int, array{page: array{host: string, slug: string}, message: string}>> $errors
     */
    private function seedResults(array $errors): void
    {
        new Filesystem()->dumpFile($this->varDir().'/page-scan--'.self::HOST, serialize($errors));
    }

    private function cleanScanState(): void
    {
        $fs = new Filesystem();
        $storage = new ProcessOutputStorage($fs, $this->varDir());
        // Per-host scope.
        $fs->remove($this->varDir().'/page-scan--'.self::HOST);
        $storage->clear('page-scanner--'.self::HOST);
        // All-hosts scope.
        $fs->remove($this->varDir().'/page-scan');
        $storage->clear('page-scanner');
    }

    private function storage(): ProcessOutputStorage
    {
        return new ProcessOutputStorage(new Filesystem(), $this->varDir());
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $url): array
    {
        $this->client->request($method, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
            'CONTENT_TYPE' => 'application/json',
        ]);

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
