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

    public function testCreatePageReturnsMinimalPayloadWithRevisionAndETag(): void
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $response = $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => 'about-'.uniqid(), 'h1' => 'About', 'locale' => 'en'],
            'body' => '# Hi',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode();
        self::assertNotEmpty($body['revision']);
        self::assertSame($body['revision'], $response->headers->get('ETag'));
        self::assertArrayHasKey('updatedAt', $body);
        // Host is in the URL, so it isn't echoed; the slug is (it may be normalized).
        self::assertArrayNotHasKey('host', $body);
        self::assertIsString($body['slug']);
        // Minimal write response: the body the client just sent isn't echoed back.
        self::assertArrayNotHasKey('body', $body);
        self::assertArrayNotHasKey('frontmatter', $body);

        $this->createdPageIds[] = $this->lookupPageId($host, $body['slug']);
    }

    public function testCreatePageWithReturnFullEchoesCompletePayload(): void
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $response = $this->request('POST', '/api/page/'.$host.'?return=full', [
            'frontmatter' => ['slug' => 'about-'.uniqid(), 'h1' => 'About', 'locale' => 'en'],
            'body' => '# Hi',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame('# Hi', $body['body']);
        self::assertIsArray($body['frontmatter']);
        self::assertSame('About', $body['frontmatter']['h1']);

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
        // Minimal write response by default: host and slug are in the URL, so omitted.
        self::assertArrayNotHasKey('host', $body);
        self::assertArrayNotHasKey('slug', $body);
        self::assertArrayNotHasKey('body', $body);
        self::assertNotSame($revision, $body['revision']);

        // The change was actually persisted (GET returns the full payload).
        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $fresh = $this->decode();
        self::assertIsArray($fresh['frontmatter']);
        self::assertSame('Updated H1', $fresh['frontmatter']['h1']);
        self::assertSame('New body content', $fresh['body']);
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

    public function testPatchAnchoredEditUpdatesBody(): void
    {
        [$host, $slug] = $this->createDraftWithBody("# Title\n\nÀ partir de 90€ par jour.\n\nFin.");
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'edits' => [['find' => 'À partir de 90€', 'replace' => 'À partir de 120€']],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode();
        self::assertArrayNotHasKey('host', $body);
        self::assertArrayNotHasKey('slug', $body);
        self::assertArrayNotHasKey('body', $body);
        self::assertNotSame($revision, $body['revision']);
        self::assertSame($body['revision'], $response->headers->get('ETag'));

        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $fresh = $this->decode();
        self::assertIsString($fresh['body']);
        self::assertStringContainsString('À partir de 120€', $fresh['body']);
        self::assertStringNotContainsString('90€', $fresh['body']);
    }

    public function testPatchWithReturnFullEchoesBody(): void
    {
        [$host, $slug] = $this->createDraftWithBody('one two three');
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug.'?return=full', [
            'edits' => [['find' => 'two', 'replace' => 'TWO']],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame('one TWO three', $body['body']);
    }

    public function testPatchAmbiguousFindReturns422(): void
    {
        [$host, $slug] = $this->createDraftWithBody("price 90€ here\nand price 90€ there");
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'edits' => [['find' => '90€', 'replace' => '120€']],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame('patch_failed', $body['error']);
        self::assertIsArray($body['edit']);
        self::assertSame(0, $body['edit']['index']);
        self::assertSame('ambiguous', $body['edit']['reason']);
        self::assertSame(2, $body['edit']['matches']);
    }

    public function testPatchReplaceAllReplacesEveryOccurrence(): void
    {
        [$host, $slug] = $this->createDraftWithBody('a a a');
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug.'?return=full', [
            'edits' => [['find' => 'a', 'replace' => 'b', 'replaceAll' => true]],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('b b b', $this->decode()['body']);
    }

    public function testPatchUnknownFindReturns422(): void
    {
        [$host, $slug] = $this->createDraftWithBody('hello world');
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'edits' => [['find' => 'absent', 'replace' => 'x']],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->decode();
        self::assertIsArray($body['edit']);
        self::assertSame('not_found', $body['edit']['reason']);
    }

    public function testPatchIsAtomicWhenLaterEditFails(): void
    {
        [$host, $slug] = $this->createDraftWithBody('keep this line');
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'edits' => [
                ['find' => 'keep', 'replace' => 'KEEP'],
                ['find' => 'missing-anchor', 'replace' => 'x'],
            ],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(422, $response->getStatusCode());
        $failure = $this->decode();
        self::assertIsArray($failure['edit']);
        self::assertSame(1, $failure['edit']['index']);

        // Nothing persisted: body and revision are untouched by the failed first edit.
        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $fresh = $this->decode();
        self::assertSame('keep this line', $fresh['body']);
        self::assertSame($revision, $fresh['revision']);
    }

    public function testPatchWithoutIfMatchReturns428(): void
    {
        [$host, $slug] = $this->createDraftWithBody('body');

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'edits' => [['find' => 'body', 'replace' => 'x']],
        ]);
        self::assertSame(428, $response->getStatusCode());
    }

    public function testPatchWithStaleIfMatchReturns409(): void
    {
        [$host, $slug] = $this->createDraftWithBody('body');

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'edits' => [['find' => 'body', 'replace' => 'x']],
        ], ['HTTP_IF_MATCH' => 'stale']);
        self::assertSame(409, $response->getStatusCode());
    }

    public function testPatchWithNeitherEditsNorFrontmatterReturns400(): void
    {
        [$host, $slug] = $this->createDraftWithBody('body');
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [], ['HTTP_IF_MATCH' => $revision]);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testPatchCombinesEditsAndFrontmatter(): void
    {
        [$host, $slug] = $this->createDraftWithBody('old body');
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'edits' => [['find' => 'old', 'replace' => 'new']],
            'frontmatter' => ['h1' => 'Patched H1'],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(200, $response->getStatusCode());

        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $fresh = $this->decode();
        self::assertSame('new body', $fresh['body']);
        self::assertIsArray($fresh['frontmatter']);
        self::assertSame('Patched H1', $fresh['frontmatter']['h1']);
    }

    public function testPatchFrontmatterOnlyLeavesBodyUntouched(): void
    {
        [$host, $slug] = $this->createDraftWithBody('original body');
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'frontmatter' => ['h1' => 'Only Frontmatter'],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(200, $response->getStatusCode());

        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $fresh = $this->decode();
        self::assertSame('original body', $fresh['body']);
        self::assertIsArray($fresh['frontmatter']);
        self::assertSame('Only Frontmatter', $fresh['frontmatter']['h1']);
    }

    public function testPatchPublishedPageRoutesToPendingModification(): void
    {
        // Default create publishes the page; the workflow gate should intercept the PATCH.
        [$host, $slug] = $this->createTestPage();
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'edits' => [['find' => 'Hello', 'replace' => 'Hi']],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(202, $response->getStatusCode());
        $body = $this->decode();
        self::assertArrayHasKey('pendingModification', $body);

        // Page body itself wasn't mutated.
        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        self::assertSame('Hello', $this->decode()['body']);
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

    /**
     * Create a draft page (no publishedAt, so the workflow gate stays out of the
     * way) with a known body, returning [host, slug].
     *
     * @return array{0: string, 1: string}
     */
    private function createDraftWithBody(string $body): array
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $slug = 'patch-'.uniqid();
        $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => $slug, 'h1' => 'Test', 'locale' => 'en', 'publishedAt' => null],
            'body' => $body,
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $created = $this->decode();
        self::assertIsString($created['slug']);
        $this->createdPageIds[] = $this->lookupPageId($host, $created['slug']);

        return [$host, $created['slug']];
    }

    private function currentRevision(string $host, string $slug): string
    {
        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $current = $this->decode();
        self::assertIsString($current['revision']);

        return $current['revision'];
    }

    private function lookupPageId(string $host, string $slug): int
    {
        $page = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $slug]);
        self::assertInstanceOf(Page::class, $page);

        return $page->id ?? 0;
    }
}
