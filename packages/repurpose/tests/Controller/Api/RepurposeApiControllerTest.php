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

        // Every pairing states whether its TTFs are really on disk, so an agent
        // never picks a font that would silently fall back to Roboto.
        $pairings = $facts['fontPairings'];
        self::assertIsArray($pairings);
        $playfair = $pairings['playfair-chivo'];
        self::assertIsArray($playfair);
        self::assertTrue($playfair['installed'], 'bundled pairings ship their TTFs');
        $rozha = $pairings['rozha-one-questrial'];
        self::assertIsArray($rozha);
        self::assertArrayHasKey('installed', $rozha);
    }

    public function testPreviewOfAnUnknownCarouselIs404(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/repurpose/999999/preview.png', [], [], $this->authServer());

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testValidateWarnsOnUnreadableContrastWithoutBlocking(): void
    {
        $spec = $this->validSpec();
        $spec['slides'] = [[
            'title' => 'Hello',
            'palette' => ['bg' => '#0b1120', 'text' => '#1e293b'],
        ]];

        $result = $this->requestJson('POST', '/api/repurpose/validate', $spec);
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), 'a low-contrast spec stays valid');
        self::assertTrue($result['valid']);
        $warnings = $result['warnings'];
        self::assertIsArray($warnings);
        self::assertNotEmpty($warnings);
        $first = $warnings[0];
        self::assertIsArray($first);
        self::assertSame('slides[0]', $first['path']);
    }

    /**
     * An unknown `creator` key silently falls back to the brand byline, so the
     * upsert response must say so — and list the keys that would have worked.
     * The test host is unconfigured, so it resolves to the default app, whose
     * config declares the `robin` creator.
     */
    public function testUpsertWarnsOnAnUnknownCreatorKeyButNotOnAKnownOne(): void
    {
        $url = '/api/repurpose/'.self::HOST.'/linkedin/'.self::PAGE;

        $spec = $this->validSpec();
        $spec['creator'] = 'nobody-configured';
        $result = $this->requestJson('PUT', $url, $spec);
        $message = $this->creatorWarning($result);
        self::assertNotNull($message, 'an unknown creator key must be flagged');
        self::assertStringContainsString('"nobody-configured"', $message);
        self::assertStringContainsString('robin', $message);
        self::assertStringContainsString('Pushword', $message);

        $spec['creator'] = 'robin';
        self::assertNull($this->creatorWarning($this->requestJson('PUT', $url, $spec)));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function creatorWarning(array $result): ?string
    {
        $warnings = $result['warnings'];
        self::assertIsArray($warnings);
        foreach ($warnings as $warning) {
            if (\is_array($warning) && 'creator' === ($warning['path'] ?? null) && \is_string($warning['message'] ?? null)) {
                return $warning['message'];
            }
        }

        return null;
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

        // Create. The response closes the agent loop on its own: persisted slide
        // count plus the studio/preview/slide URLs to look at the result.
        $created = $this->requestJson('PUT', $url, $this->validSpec());
        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $id = $created['id'];
        self::assertIsInt($id);
        self::assertSame(1, $created['slides']);
        self::assertIsArray($created['warnings']);
        self::assertIsString($created['studioUrl']);
        self::assertStringContainsString('/admin/repurpose/studio/'.$id, $created['studioUrl']);
        self::assertIsString($created['previewUrl']);
        self::assertStringContainsString('/api/repurpose/'.$id.'/preview.png', $created['previewUrl']);
        $slideUrls = $created['slideUrls'];
        self::assertIsArray($slideUrls);
        self::assertCount(1, $slideUrls);
        self::assertIsString($slideUrls[0]);
        self::assertStringContainsString('/api/repurpose/'.$id.'/slide-1.svg', $slideUrls[0]);

        // Read back.
        $read = $this->requestJson('GET', $url);
        self::assertSame(self::PAGE, $read['page']);
        self::assertSame('linkedin', $read['network']);
        self::assertSame('linkedin-4-5', $read['format']);
        self::assertSame(1, $read['slides']);
        self::assertIsString($read['studioUrl']);
        self::assertStringContainsString('/admin/repurpose/studio/'.$id, $read['studioUrl']);

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

        // Whole-deck preview: a PNG contact sheet when Chromium is on the host,
        // an explicit 501 pointing back at the slide SVGs otherwise.
        $this->client->request(Request::METHOD_GET, '/api/repurpose/'.$id.'/preview.png', [], [], $this->authServer());
        $preview = $this->client->getResponse();
        if (Response::HTTP_OK === $preview->getStatusCode()) {
            self::assertSame('image/png', $preview->headers->get('Content-Type'));
            self::assertStringStartsWith("\x89PNG", (string) $preview->getContent());
        } else {
            self::assertSame(Response::HTTP_NOT_IMPLEMENTED, $preview->getStatusCode());
            self::assertStringContainsString('slideUrls', (string) $preview->getContent());
        }

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
