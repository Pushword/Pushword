<?php

namespace Pushword\Api\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class PageApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    /** @var list<int> */
    private array $createdPageIds = [];

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'page-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $this->em->persist($user);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        foreach ($this->createdPageIds as $id) {
            $page = $em->getRepository(Page::class)->find($id);
            if ($page instanceof Page) {
                $em->remove($page);
            }
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

    public function testCreatePageReturnsRevisionAndETag(): void
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $response = $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => 'about-'.uniqid(), 'h1' => 'About', 'locale' => 'en'],
            'body' => '# Hi',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame($host, $body['host']);
        self::assertNotEmpty($body['revision']);
        self::assertSame('# Hi', $body['body']);
        self::assertSame($body['revision'], $response->headers->get('ETag'));

        self::assertIsString($body['slug']);
        $this->createdPageIds[] = $this->lookupPageId($host, $body['slug']);
    }

    public function testGetPageRoundtrips(): void
    {
        [$host, $slug] = $this->createTestPage();

        $response = $this->request('GET', '/api/page/'.$host.'/'.$slug);
        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame($host, $body['host']);
        self::assertSame($slug, $body['slug']);
        self::assertNotEmpty($body['revision']);
        self::assertSame($body['revision'], $response->headers->get('ETag'));
        self::assertArrayHasKey('frontmatter', $body);
        self::assertArrayHasKey('body', $body);
    }

    public function testPutWithoutIfMatchReturns428(): void
    {
        [$host, $slug] = $this->createTestPage();

        $response = $this->request('PUT', '/api/page/'.$host.'/'.$slug, ['frontmatter' => ['h1' => 'Updated']]);
        self::assertSame(428, $response->getStatusCode());
    }

    public function testPutWithStaleIfMatchReturns409WithCurrent(): void
    {
        [$host, $slug] = $this->createTestPage();

        $response = $this->request('PUT', '/api/page/'.$host.'/'.$slug, ['frontmatter' => ['h1' => 'X']], [
            'HTTP_IF_MATCH' => 'totally-wrong-revision',
        ]);
        self::assertSame(409, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame('revision_mismatch', $body['error']);
        self::assertSame('totally-wrong-revision', $body['your_revision']);
        self::assertIsString($body['current_revision']);
        self::assertNotEmpty($body['current_revision']);
        self::assertIsArray($body['current']);
        self::assertArrayHasKey('frontmatter', $body['current']);
    }

    public function testPutWithMatchingIfMatchUpdatesPage(): void
    {
        // Create as draft (no publishedAt) so the workflow gate doesn't route us to PendingModification.
        [$host, $slug] = $this->createTestPage(['publishedAt' => null]);

        // Re-fetch to get the canonical revision (DB roundtrip may truncate microseconds).
        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $current = $this->decode();
        self::assertIsString($current['revision']);
        $revision = $current['revision'];

        $response = $this->request('PUT', '/api/page/'.$host.'/'.$slug, [
            'frontmatter' => ['h1' => 'Updated H1'],
            'body' => 'New body content',
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode();
        self::assertIsArray($body['frontmatter']);
        self::assertSame('Updated H1', $body['frontmatter']['h1']);
        self::assertSame('New body content', $body['body']);
        self::assertNotSame($revision, $body['revision']);
    }

    public function testPutPublishedPageRoutesToPendingModification(): void
    {
        // Default create publishes the page; workflow gate should then intercept the PUT.
        [$host, $slug] = $this->createTestPage();
        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $current = $this->decode();
        self::assertIsString($current['revision']);
        $revision = $current['revision'];

        $response = $this->request('PUT', '/api/page/'.$host.'/'.$slug, [
            'frontmatter' => ['h1' => 'Through workflow'],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(202, $response->getStatusCode());
        $body = $this->decode();
        self::assertArrayHasKey('pendingModification', $body);
        self::assertArrayHasKey('page', $body);
        self::assertIsArray($body['page']);
        self::assertIsArray($body['page']['frontmatter']);
        // Page itself wasn't mutated — h1 should be unchanged from the create-time value.
        self::assertSame('Test', $body['page']['frontmatter']['h1']);
    }

    public function testPreviewRendersMarkdown(): void
    {
        $this->request('POST', '/api/page/preview', [
            'host' => 'example.com',
            'slug' => 'preview',
            'frontmatter' => ['h1' => 'Hello'],
            'body' => '# Hi there',
        ]);
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertIsString($body['html']);
        self::assertStringContainsString('<h1', $body['html']);
        self::assertIsArray($body['frontmatter']);
        self::assertSame('Hello', $body['frontmatter']['h1']);
    }

    public function testSearchReturnsPaginatedResults(): void
    {
        $this->request('GET', '/api/page/search?per_page=2');
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertArrayHasKey('items', $body);
        self::assertArrayHasKey('total', $body);
        self::assertSame(2, $body['per_page']);
    }

    public function testGetUnknownPageReturns404(): void
    {
        $response = $this->request('GET', '/api/page/nope.example.com/nothing-here');
        self::assertSame(404, $response->getStatusCode());
    }

    public function testWithoutTokenReturns401(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/page/search');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $extraHeaders
     */
    private function request(string $method, string $url, array $body = [], array $extraHeaders = []): Response
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken, 'CONTENT_TYPE' => 'application/json'] + $extraHeaders;
        $this->client->request($method, $url, [], [], $server, [] === $body ? '' : (string) json_encode($body));

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $extraFrontmatter
     *
     * @return array{0: string, 1: string, 2: string} host, slug, revision
     */
    private function createTestPage(array $extraFrontmatter = []): array
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $slug = 'about-'.uniqid();
        $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => $slug, 'h1' => 'Test', 'locale' => 'en'] + $extraFrontmatter,
            'body' => 'Hello',
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $body = $this->decode();
        self::assertIsString($body['slug']);
        self::assertIsString($body['revision']);
        $this->createdPageIds[] = $this->lookupPageId($host, $body['slug']);

        return [$host, $body['slug'], $body['revision']];
    }

    private function lookupPageId(string $host, string $slug): int
    {
        $page = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $slug]);
        self::assertInstanceOf(Page::class, $page);

        return $page->id ?? 0;
    }
}
