<?php

namespace Pushword\Repurpose\Tests\Controller\Api;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Repurpose\Entity\SocialPost;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The token-authenticated REST surface — the package's primary agent-facing
 * interface. Exercises the full authoring loop: fetch the facts (schema,
 * networks), validate a spec, upsert it by its natural key, read it back, reframe
 * an image with PATCH, render a slide, then delete. The validator is shared with
 * the CLI and the renderer, so a green loop here proves the whole contract.
 */
#[Group('integration')]
final class RepurposeApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private string $testToken = '';

    private string $testUserEmail = '';

    private const string HOST = 'repurpose-api-test.example';

    private const string PAGE = 'blog/test-article';

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'repurpose-api-test-'.uniqid().'@example.com';

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $em->persist($user);
        $em->flush();
    }

    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        foreach ($em->getRepository(SocialPost::class)->findBy(['host' => self::HOST]) as $post) {
            $em->remove($post);
        }

        /** @var class-string<User> $userClass */
        $userClass = $container->getParameter('pw.entity_user');
        $user = $em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $em->remove($user);
        }

        $em->flush();
        parent::tearDown();
    }

    public function testUnauthenticatedIsRejected(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/repurpose/networks');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testSchemaAndNetworksExposeTheFacts(): void
    {
        $schema = $this->requestJson('GET', '/api/repurpose/schema');
        self::assertArrayHasKey('properties', $schema);

        $facts = $this->requestJson('GET', '/api/repurpose/networks');
        self::assertArrayHasKey('formats', $facts);
        self::assertArrayHasKey('networks', $facts);
        $networks = $facts['networks'];
        self::assertIsArray($networks);
        self::assertArrayHasKey('linkedin', $networks);
    }

    public function testValidateAcceptsAGoodSpecAndRejectsABadOne(): void
    {
        $valid = $this->requestJson('POST', '/api/repurpose/validate', $this->validSpec());
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertTrue($valid['valid']);

        // linkedin-4-5 is not an instagram format.
        $bad = $this->validSpec();
        $bad['network'] = 'instagram';
        $errors = $this->requestJson('POST', '/api/repurpose/validate', $bad);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertNotEmpty($errors['violations']);
    }

    public function testUpsertReadReframeRenderDelete(): void
    {
        $url = '/api/repurpose/'.self::HOST.'/linkedin/'.self::PAGE;

        // Create.
        $created = $this->requestJson('PUT', $url, $this->validSpec());
        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $id = $created['id'];
        self::assertIsInt($id);

        // Read back.
        $read = $this->requestJson('GET', $url);
        self::assertSame(self::PAGE, $read['page']);
        self::assertSame('linkedin', $read['network']);
        self::assertSame('linkedin-4-5', $read['format']);

        // Reframe: PATCH the same key with a new zoom, confirm it persisted.
        $reframed = $this->validSpec();
        $reframed['slides'] = [['title' => 'Hello', 'image' => ['media' => 'photo.jpg', 'zoom' => 1.8]]];
        $this->requestJson('PATCH', $url, $reframed);
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->client->request(Request::METHOD_GET, $url, [], [], $this->authServer());
        self::assertStringContainsString('"zoom":1.8', (string) $this->client->getResponse()->getContent());

        // Render a slide to a self-contained SVG.
        $this->client->request(Request::METHOD_GET, '/api/repurpose/'.$id.'/slide-1.svg', [], [], $this->authServer());
        $svg = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $svg->getStatusCode());
        self::assertSame('image/svg+xml', $svg->headers->get('Content-Type'));
        self::assertStringContainsString('<svg', (string) $svg->getContent());

        // Delete, then confirm it is gone.
        $this->client->request(Request::METHOD_DELETE, $url, [], [], $this->authServer());
        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        $this->requestJson('GET', $url);
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function validSpec(): array
    {
        return [
            'page' => self::PAGE,
            'network' => 'linkedin',
            'format' => 'linkedin-4-5',
            'slides' => [
                ['title' => 'Hello', 'image' => ['media' => 'photo.jpg', 'zoom' => 1.1]],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authServer(): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $url, array $body = []): array
    {
        $this->client->request($method, $url, [], [], $this->authServer(), [] === $body ? null : (string) json_encode($body));

        $content = (string) $this->client->getResponse()->getContent();
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
