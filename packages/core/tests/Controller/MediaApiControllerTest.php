<?php

namespace Pushword\Core\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class MediaApiControllerTest extends WebTestCase
{
    use PathTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private ?User $testUser = null;

    private string $testToken = '';

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->ensureMediaFileExists();

        $this->testToken = bin2hex(random_bytes(32));
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $this->testUser = new $userClass();
        $this->testUser->email = 'media-api-test-'.uniqid().'@example.com';
        $this->testUser->setPassword('hashed-password');
        $this->testUser->apiToken = $this->testToken;

        $this->em->persist($this->testUser);
        $this->em->flush();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (null !== $this->testUser) {
            $this->em->remove($this->testUser);
            $this->em->flush();
            $this->testUser = null;
        }

        parent::tearDown();
    }

    public function testGetRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/media/piedweb-logo.png');
        self::assertResponseStatusCodeSame(401);
    }

    public function testGetWithInvalidToken(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/media/piedweb-logo.png', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testGetReturns404ForUnknownMedia(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/media/nonexistent.png', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testGetReturnsMediaMetadata(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/media/piedweb-logo.png', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $response = json_decode($content, true);
        self::assertIsArray($response);
        self::assertSame('piedweb-logo.png', $response['filename']);
        self::assertArrayHasKey('mimeType', $response);
        self::assertArrayHasKey('size', $response);
        self::assertArrayHasKey('alt', $response);
        self::assertArrayHasKey('alts', $response);
        self::assertArrayHasKey('tags', $response);
        self::assertArrayHasKey('image', $response);
    }

    public function testPostUpdatesAlt(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/media/piedweb-logo.png', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], (string) json_encode(['alt' => 'Updated alt text']));

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $response = json_decode($content, true);
        self::assertIsArray($response);
        self::assertSame('Updated alt text', $response['alt']);
    }

    public function testPostUpdatesAlts(): void
    {
        $alts = ['fr' => 'Logo en français', 'de' => 'Logo auf Deutsch'];
        $this->client->request(Request::METHOD_POST, '/api/media/piedweb-logo.png', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], (string) json_encode(['alts' => $alts]));

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $response = json_decode($content, true);
        self::assertIsArray($response);
        self::assertSame($alts, $response['alts']);
    }

    public function testPostUpdatesTags(): void
    {
        $tags = ['landscape', 'logo'];
        $this->client->request(Request::METHOD_POST, '/api/media/piedweb-logo.png', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], (string) json_encode(['tags' => $tags]));

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $response = json_decode($content, true);
        self::assertIsArray($response);
        self::assertIsArray($response['tags']);
        self::assertContains('landscape', $response['tags']);
        self::assertContains('logo', $response['tags']);
    }

    public function testPostWithInvalidJsonReturns400(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/media/piedweb-logo.png', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], 'not-valid-json');

        self::assertResponseStatusCodeSame(400);
    }
}
