<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Pushword\Core\Entity\User;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class FlatLockApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private FlatLockManager $lockManager;

    private ?User $testUser = null;

    private string $testToken = '';

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;

        $lockManager = self::getContainer()->get(FlatLockManager::class);
        \assert($lockManager instanceof FlatLockManager);
        $this->lockManager = $lockManager;

        // Create test user with API token
        $this->testToken = bin2hex(random_bytes(32));
        $this->testUser = new User();
        $this->testUser->email = 'api-test-'.uniqid().'@example.com';
        $this->testUser->setPassword('hashed-password');
        $this->testUser->apiToken = $this->testToken;

        $this->em->persist($this->testUser);
        $this->em->flush();
    }

    #[Override]
    protected function tearDown(): void
    {
        // Clean up test user
        if (null !== $this->testUser) {
            $this->em->remove($this->testUser);
            $this->em->flush();
            $this->testUser = null;
        }

        // Clean up any locks
        $this->lockManager->releaseLock('test.example.com');
        $this->lockManager->releaseLock(null);

        parent::tearDown();
    }

    public function testLockRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/flat/lock', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['host' => 'test.example.com']));

        self::assertResponseStatusCodeSame(401);

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        /** @var array{success: bool, message: string} $response */
        $response = json_decode($content, true);
        self::assertFalse($response['success']);
        self::assertStringContainsString('Invalid', $response['message']);
    }

    public function testLockWithValidToken(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/flat/lock', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], json_encode([
            'host' => 'test.example.com',
            'reason' => 'Test lock',
            'ttl' => 3600,
        ]));

        self::assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        self::assertTrue($response['success']);
        self::assertSame('Lock acquired', $response['message']);
        self::assertArrayHasKey('lockInfo', $response);
        self::assertTrue($response['lockInfo']['locked']);
    }

    public function testLockConflictWhenAlreadyLocked(): void
    {
        // First acquire a lock
        $this->lockManager->acquireWebhookLock('test.example.com', 'First lock', 3600, 'other@example.com');

        // Try to acquire another lock
        $this->client->request(Request::METHOD_POST, '/api/flat/lock', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], json_encode(['host' => 'test.example.com']));

        self::assertResponseStatusCodeSame(409);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        self::assertFalse($response['success']);
        self::assertSame('Lock already held', $response['message']);
    }

    public function testUnlockRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/flat/unlock', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['host' => 'test.example.com']));

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnlockWebhookLock(): void
    {
        self::assertNotNull($this->testUser);
        $this->lockManager->acquireWebhookLock('test.example.com', 'Test', 3600, $this->testUser->email);

        $this->client->request(Request::METHOD_POST, '/api/flat/unlock', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], json_encode(['host' => 'test.example.com']));

        self::assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        self::assertTrue($response['success']);
        self::assertSame('Lock released', $response['message']);
        self::assertFalse($this->lockManager->isLocked('test.example.com'));
    }

    public function testCannotUnlockNonWebhookLock(): void
    {
        // Acquire a manual lock (not webhook)
        $this->lockManager->acquireLock('test.example.com', FlatLockManager::LOCK_TYPE_MANUAL);

        // Try to unlock via API
        $this->client->request(Request::METHOD_POST, '/api/flat/unlock', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], json_encode(['host' => 'test.example.com']));

        self::assertResponseStatusCodeSame(403);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        self::assertFalse($response['success']);
        self::assertStringContainsString('Cannot unlock non-webhook lock', $response['message']);
    }

    public function testStatusRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/flat/status?host=test.example.com');

        self::assertResponseStatusCodeSame(401);
    }

    public function testStatusWhenNotLocked(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/flat/status?host=test.example.com', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);

        self::assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        self::assertFalse($response['locked']);
        self::assertFalse($response['isWebhookLock']);
    }

    public function testStatusWhenLocked(): void
    {
        self::assertNotNull($this->testUser);
        $this->lockManager->acquireWebhookLock('test.example.com', 'Test', 3600, $this->testUser->email);

        $this->client->request(Request::METHOD_GET, '/api/flat/status?host=test.example.com', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);

        self::assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        self::assertTrue($response['locked']);
        self::assertTrue($response['isWebhookLock']);
        self::assertGreaterThan(0, $response['remainingSeconds']);
        self::assertArrayHasKey('lockInfo', $response);
    }

    public function testLockWithNullHostLocksGlobally(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/flat/lock', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], json_encode([
            'reason' => 'Global lock',
        ]));

        self::assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        self::assertTrue($response['success']);

        // Verify global lock is in effect
        self::assertTrue($this->lockManager->isLocked(null));
    }

    public function testLockWithCustomTtl(): void
    {
        $customTtl = 1800;

        $this->client->request(Request::METHOD_POST, '/api/flat/lock', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], json_encode([
            'host' => 'test.example.com',
            'ttl' => $customTtl,
        ]));

        self::assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($customTtl, $response['lockInfo']['ttl']);
    }
}
