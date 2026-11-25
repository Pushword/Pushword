<?php

declare(strict_types=1);

namespace Pushword\Api\Tests\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Pushword\Core\Collaboration\DraftExchange;
use Pushword\Core\Collaboration\LivePresence;
use Pushword\Core\Collaboration\LiveProjection;
use Pushword\Core\Entity\LiveEditingState;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
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
            $state = $em->find(LiveEditingState::class, 'page:'.$id);
            if ($state instanceof LiveEditingState) {
                $em->remove($state);
            }

            $page = $em->getRepository(Page::class)->find($id);
            if ($page instanceof Page) {
                // Unlink first: translation rows point at the page from both sides.
                foreach ($page->translations->toArray() as $translation) {
                    $page->removeTranslation($translation);
                }

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

    #[TestWith([Request::METHOD_PUT])]
    #[TestWith([Request::METHOD_PATCH])]
    public function testLiveStatusAndJsonUpdatesFollowCollaborationPresence(string $method): void
    {
        [$host, $slug, $revision] = $this->createTestPage();
        $id = $this->lookupPageId($host, $slug);
        $path = '/api/page/'.$host.'/'.$slug;
        $status = $this->request('GET', $path.'?return=editing');
        self::assertSame(['id' => $id, 'collaborative' => false], $this->decode());
        self::assertStringContainsString('no-store', (string) $status->headers->get('Cache-Control'));

        $presence = self::getContainer()->get(LivePresence::class);
        $join = Request::create('/draft', Request::METHOD_POST, server: ['HTTP_X_COLLABORATION_TAB' => str_repeat('a', 32)]);
        $ready = static fn (): JsonResponse => new JsonResponse([]);
        $presence->sync($join, 'page:'.$id, $ready);
        $this->request('GET', $path.'?return=editing');
        self::assertSame(['id' => $id, 'collaborative' => true], $this->decode());

        $edit = ['frontmatter' => ['h1' => 'Updated title']];
        $headers = ['HTTP_IF_MATCH' => $revision];
        self::assertSame(Response::HTTP_LOCKED, $this->request($method, $path, $edit, $headers)->getStatusCode());
        self::assertSame(['error' => 'collaboration_active', 'id' => $id], $this->decode());
        self::assertSame($revision, $this->currentRevision($host, $slug));

        $join->setMethod(Request::METHOD_DELETE);
        $presence->sync($join, 'page:'.$id, $ready);
        $this->request('GET', $path.'?return=editing');
        self::assertSame(['id' => $id, 'collaborative' => false], $this->decode());
        self::assertSame(Response::HTTP_OK, $this->request($method, $path, $edit, $headers)->getStatusCode());
        $this->request('GET', $path);
        $frontmatter = $this->decode()['frontmatter'];
        self::assertIsArray($frontmatter);
        self::assertSame('Updated title', $frontmatter['h1']);
    }

    #[TestWith([Request::METHOD_PUT, false])]
    #[TestWith([Request::METHOD_PUT, true])]
    #[TestWith([Request::METHOD_PATCH, false])]
    #[TestWith([Request::METHOD_PATCH, true])]
    public function testConcurrentWriteAfterIfMatchReturnsTheWinningPage(string $method, bool $deleted): void
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => 'concurrent', 'h1' => 'Initial title', 'locale' => 'en'],
            'body' => 'Initial body',
        ]);
        $id = $this->lookupPageId($host, 'concurrent');
        $this->createdPageIds[] = $id;
        $revision = $this->currentRevision($host, 'concurrent');
        $body = ['body' => 'Agent edit'];
        if (Request::METHOD_PATCH === $method) {
            $this->request(Request::METHOD_POST, '/api/pages/'.$id.'/draft', ['after' => 0]);
            self::assertResponseIsSuccessful();
            $body = ['edits' => [['find' => 'Initial body', 'replace' => 'Agent edit']]];
        }

        $listener = new readonly class($this->em->getConnection(), $id, $deleted) {
            public function __construct(private Connection $connection, private int $id, private bool $deleted)
            {
            }

            public function preFlush(): void
            {
                if ($this->deleted) {
                    $this->connection->delete('page', ['id' => $this->id]);

                    return;
                }

                $this->connection->executeStatement('UPDATE page SET h1 = ?, write_version = write_version + 1 WHERE id = ?', ['Saved concurrently', $this->id]);
            }
        };
        $events = $this->em->getEventManager();
        $events->addEventListener([Events::preFlush], $listener);

        try {
            $response = $this->request($method, '/api/page/'.$host.'/concurrent', $body, ['HTTP_IF_MATCH' => $revision]);
        } finally {
            $events->removeEventListener([Events::preFlush], $listener);
        }

        if (Request::METHOD_PATCH === $method) {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $state = $em->find(LiveEditingState::class, 'page:'.$id);
            self::assertInstanceOf(LiveEditingState::class, $state);
            self::assertSame(0, $state->cursor);
            self::assertNull($state->snapshot);
        }

        if ($deleted) {
            self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

            return;
        }

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $conflict = $this->decode();
        self::assertSame('revision_mismatch', $conflict['error']);
        self::assertSame($revision, $conflict['your_revision']);
        self::assertNotSame($revision, $conflict['current_revision']);
        self::assertIsArray($conflict['current']);
        self::assertSame('Initial body', $conflict['current']['body']);
        self::assertIsArray($conflict['current']['frontmatter']);
        self::assertSame('Saved concurrently', $conflict['current']['frontmatter']['h1']);
    }

    public function testAWriteDatedInTheFutureSaysThePageIsScheduled(): void
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => 'later-'.uniqid(), 'h1' => 'Later', 'locale' => 'en', 'publishedAt' => '2099-01-01 10:00'],
            'body' => '# Later',
        ]);
        $created = $this->decode();
        self::assertIsString($created['slug']);
        $this->createdPageIds[] = $this->lookupPageId($host, $created['slug']);

        self::assertTrue($created['scheduled']);
        // On the editors' clock, the server's in this app.
        self::assertSame('2099-01-01T10:00:00+00:00', $created['publishedAt']);

        $url = '/api/page/'.$host.'/'.$created['slug'];
        $this->request('PATCH', $url.'?return=full', [
            'frontmatter' => ['publishedAt' => '2099-02-01T10:00:00+01:00'],
        ], ['HTTP_IF_MATCH' => $this->currentRevision($host, $created['slug'])]);
        $full = $this->decode();
        self::assertTrue($full['scheduled']);
        self::assertSame('2099-02-01T09:00:00+00:00', $full['publishedAt']);

        $this->request('PATCH', $url, [
            'frontmatter' => ['publishedAt' => '2020-01-01 10:00'],
        ], ['HTTP_IF_MATCH' => $this->currentRevision($host, $created['slug'])]);
        self::assertArrayNotHasKey('scheduled', $this->decode(), 'a past date is a publication');

        $this->request('PATCH', $url, [
            'frontmatter' => ['publishedAt' => 'draft'],
        ], ['HTTP_IF_MATCH' => $this->currentRevision($host, $created['slug'])]);
        self::assertArrayNotHasKey('scheduled', $this->decode(), 'a draft has no date to announce');
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

    public function testPutViolatingTheDeclaredSchemaReturns422(): void
    {
        // The update path (shared by PUT and PATCH) must block like create.
        [$host, $slug] = $this->createTestPage();
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PUT', '/api/page/'.$host.'/'.$slug, [
            'frontmatter' => ['priority' => -3],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('priority', (string) $response->getContent());
    }

    public function testWriteResponsesWarnAboutUndeclaredKeys(): void
    {
        // A near-miss key (toc_titel for tocTitle) stores fine but rides the
        // write response as an informational warning — same net as the flat
        // import summary.
        $this->createTestPage(['toc_titel' => 'Sommaire']);

        $created = $this->decode();
        self::assertIsArray($created['warnings']);
        $undeclared = $created['warnings']['undeclared'];
        self::assertIsArray($undeclared);
        self::assertContains('toc_titel', $undeclared);

        // A clean write carries no warnings key at all.
        $this->createTestPage();
        self::assertArrayNotHasKey('warnings', $this->decode());
    }

    public function testWriteResponsesWarnAboutMissingRequiredKeys(): void
    {
        // admin-block-editor.test declares author as required. Leaving it out
        // stores fine — required reports, it does not gate — but the write
        // response says what the page lacks.
        $slug = 'missing-required-'.uniqid();
        $response = $this->request('POST', '/api/page/admin-block-editor.test', [
            'frontmatter' => ['slug' => $slug, 'h1' => 'Test', 'locale' => 'en'],
            'body' => 'Hello',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode();
        self::assertIsString($body['slug']);
        $this->createdPageIds[] = $this->lookupPageId('admin-block-editor.test', $body['slug']);

        self::assertIsArray($body['warnings']);
        self::assertSame(['author'], $body['warnings']['missingRequired']);
    }

    public function testCreatePageViolatingTheDeclaredSchemaReturns422(): void
    {
        // An unknown host resolves to the default app (localhost.dev), whose
        // schema declares priority as a Positive int.
        $host = 'api-test-'.uniqid().'.example.com';
        $response = $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => 'about-'.uniqid(), 'h1' => 'About', 'locale' => 'en', 'priority' => -3],
            'body' => '# Hi',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('priority', (string) $response->getContent());
    }

    public function testCreatePageRejectsUnresolvableConverterValueWith422(): void
    {
        // "banana" is not a mainImageFormat label, key, number or human name, so
        // it cannot resolve to the integer-backed property. It must be rejected at
        // the boundary rather than stored as a string that crashes the render.
        $host = 'api-test-'.uniqid().'.example.com';
        $slug = 'about-'.uniqid();
        $response = $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => $slug, 'h1' => 'About', 'locale' => 'en', 'mainImageFormat' => 'banana'],
            'body' => '# Hi',
        ]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame('invalid_frontmatter', $body['error']);
        self::assertSame('mainImageFormat', $body['key']);

        // The invalid payload persisted nothing.
        self::assertSame(404, $this->request('GET', '/api/page/'.$host.'/'.$slug)->getStatusCode());
    }

    public function testPutRejectsUnresolvableConverterValueWith422(): void
    {
        // The 422 guard also covers the update path, not just create.
        [$host, $slug] = $this->createTestPage(['publishedAt' => null]);
        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PUT', '/api/page/'.$host.'/'.$slug, [
            'frontmatter' => ['mainImageFormat' => 'banana'],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame('invalid_frontmatter', $body['error']);
        self::assertSame('mainImageFormat', $body['key']);
    }

    public function testCreatePageAcceptsHumanNamedConverterValue(): void
    {
        // The None format's label is translated per locale; a client sending the
        // English name "None" must resolve to its integer value (1) whatever the
        // current locale, not be rejected.
        $host = 'api-test-'.uniqid().'.example.com';
        $slug = 'none-'.uniqid();
        $response = $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => $slug, 'h1' => 'No hero', 'locale' => 'en', 'mainImageFormat' => 'None'],
            'body' => '# Hi',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $this->createdPageIds[] = $this->lookupPageId($host, $slug);

        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $fresh = $this->decode();
        self::assertIsArray($fresh['frontmatter']);
        self::assertIsArray($fresh['frontmatter']['customProperties']);
        self::assertSame(1, $fresh['frontmatter']['customProperties']['mainImageFormat']);
    }

    public function testCreatePageAcceptsValidConverterValue(): void
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $slug = 'hero-'.uniqid();
        $response = $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => $slug, 'h1' => 'Hero', 'locale' => 'en', 'mainImageFormat' => 2],
            'body' => '# Hi',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $this->createdPageIds[] = $this->lookupPageId($host, $slug);

        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $fresh = $this->decode();
        self::assertIsArray($fresh['frontmatter']);
        self::assertIsArray($fresh['frontmatter']['customProperties']);
        self::assertSame(2, $fresh['frontmatter']['customProperties']['mainImageFormat']);
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
        // Create as draft (no publishedAt).
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

    public function testPutOmittingCustomPropertiesKeepsThem(): void
    {
        // Regression: a PUT that doesn't resend customProperties must not wipe
        // them. Validation used to reconcile against an empty YAML surface and
        // delete every unmanaged custom property.
        $host = 'api-test-'.uniqid().'.example.com';
        $slug = 'product-'.uniqid();
        $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => $slug, 'h1' => 'Product', 'locale' => 'en', 'customProperties' => ['productCode' => 'ABC-123']],
            'body' => 'Hello',
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $created = $this->decode();
        self::assertIsString($created['slug']);
        $slug = $created['slug'];
        $this->createdPageIds[] = $this->lookupPageId($host, $slug);

        $revision = $this->currentRevision($host, $slug);

        $response = $this->request('PUT', '/api/page/'.$host.'/'.$slug, [
            'frontmatter' => ['h1' => 'Renamed Product'],
        ], ['HTTP_IF_MATCH' => $revision]);
        self::assertSame(200, $response->getStatusCode());

        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $fresh = $this->decode();
        self::assertIsArray($fresh['frontmatter']);
        self::assertSame('Renamed Product', $fresh['frontmatter']['h1']);
        self::assertSame(['productCode' => 'ABC-123'], $fresh['frontmatter']['customProperties']);
    }

    public function testPutWithBareTopLevelCustomPropertyKeysPersistsThem(): void
    {
        // Regression: the flat exporter unpacks customProperties to bare top-level
        // frontmatter keys, so an editor that PUTs a payload built from a .md
        // snapshot sends e.g. searchExcerpt/targetKeyword flat. They used to be
        // silently dropped (only converter-backed keys like mainImageFormat
        // survived); they must now round-trip into customProperties.
        [$host, $slug, $revision] = $this->createTestPage();

        $response = $this->request('PUT', '/api/page/'.$host.'/'.$slug, [
            'frontmatter' => [
                'h1' => 'About us',
                'searchExcerpt' => 'A short SEO summary.',
                'targetKeyword' => 'about us',
            ],
        ], ['HTTP_IF_MATCH' => $revision]);
        self::assertSame(200, $response->getStatusCode());

        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $fresh = $this->decode();
        self::assertIsArray($fresh['frontmatter']);
        self::assertSame('About us', $fresh['frontmatter']['h1']);
        self::assertIsArray($fresh['frontmatter']['customProperties']);
        self::assertSame('A short SEO summary.', $fresh['frontmatter']['customProperties']['searchExcerpt']);
        self::assertSame('about us', $fresh['frontmatter']['customProperties']['targetKeyword']);
    }

    public function testPatchLinksTranslationsAcrossHosts(): void
    {
        // Regression: `translations` was exported but never applied, so POST/PUT/PATCH
        // all answered 200 and left the pair unlinked — the language switcher stayed
        // empty and the pages could only be paired from the admin.
        $sibling = 'about-'.uniqid();
        $this->request('POST', '/api/page/pushword.piedweb.com', [
            'frontmatter' => ['slug' => $sibling, 'h1' => 'About', 'locale' => 'en'],
            'body' => 'Hello',
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $this->createdPageIds[] = $this->lookupPageId('pushword.piedweb.com', $sibling);

        [$host, $slug, $revision] = $this->createTestPage(['locale' => 'fr']);

        $reference = 'pushword.piedweb.com/'.$sibling;
        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'frontmatter' => ['translations' => [$reference]],
        ], ['HTTP_IF_MATCH' => $revision]);
        self::assertSame(200, $response->getStatusCode());

        $this->request('GET', '/api/page/'.$host.'/'.$slug);
        $fresh = $this->decode();
        self::assertIsArray($fresh['frontmatter']);
        self::assertSame([$reference], $fresh['frontmatter']['translations']);

        // The sibling gained the reverse link: one call pairs both directions.
        $this->request('GET', '/api/page/pushword.piedweb.com/'.$sibling);
        $other = $this->decode();
        self::assertIsArray($other['frontmatter']);
        self::assertSame([$host.'/'.$slug], $other['frontmatter']['translations']);
    }

    public function testCreateLinksTranslationOnTheSameHost(): void
    {
        // Regression: the URL host was assigned *after* the frontmatter was applied,
        // so a same-host ref resolved against an empty host and found nothing.
        $host = 'api-test-'.uniqid().'.example.com';
        $english = 'about-'.uniqid();
        $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => $english, 'h1' => 'About', 'locale' => 'en'],
            'body' => 'Hello',
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $this->createdPageIds[] = $this->lookupPageId($host, $english);

        $french = 'a-propos-'.uniqid();
        $response = $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => $french, 'h1' => 'À propos', 'locale' => 'fr', 'translations' => [$english]],
            'body' => 'Bonjour',
        ]);
        self::assertSame(201, $response->getStatusCode());
        $this->createdPageIds[] = $this->lookupPageId($host, $french);

        $this->request('GET', '/api/page/'.$host.'/'.$french);
        $fresh = $this->decode();
        self::assertIsArray($fresh['frontmatter']);
        self::assertSame([$english], $fresh['frontmatter']['translations']);
    }

    public function testPatchRejectsUnknownTranslationReference(): void
    {
        [$host, $slug, $revision] = $this->createTestPage();

        // A ref pointing nowhere is a loud 422, never a 200 that drops the key.
        $response = $this->request('PATCH', '/api/page/'.$host.'/'.$slug, [
            'frontmatter' => ['translations' => ['ghost-'.uniqid()]],
        ], ['HTTP_IF_MATCH' => $revision]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame('invalid_frontmatter', $body['error']);
        self::assertSame('translations', $body['key']);
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

    public function testSearchFiltersHeldPages(): void
    {
        [$host] = $this->createTestPage(['holdPublication' => true]);

        $this->request('GET', '/api/page/search?held=true&host='.$host);
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertIsArray($body['items']);
        self::assertCount(1, $body['items']);
        self::assertIsArray($body['items'][0]);
        self::assertTrue($body['items'][0]['holdPublication']);

        $this->request('GET', '/api/page/search?held=false&host='.$host);
        $body = $this->decode();
        self::assertIsArray($body['items']);
        self::assertCount(0, $body['items']);
    }

    public function testSearchHeldFalseReturnsReleasedPages(): void
    {
        [$host] = $this->createTestPage();

        $this->request('GET', '/api/page/search?held=false&host='.$host);
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertIsArray($body['items']);
        self::assertCount(1, $body['items']);
        self::assertIsArray($body['items'][0]);
        self::assertFalse($body['items'][0]['holdPublication']);

        $this->request('GET', '/api/page/search?held=true&host='.$host);
        $body = $this->decode();
        self::assertIsArray($body['items']);
        self::assertCount(0, $body['items']);
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

    #[TestWith([true])]
    #[TestWith([false])]
    public function testMarkdownPatchUpdatesTheSameDraftWithOrWithoutPresence(bool $present): void
    {
        [$host, $slug] = $this->createDraftWithBody('Start middle end');
        $revision = $this->currentRevision($host, $slug);
        $page = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $slug]);
        self::assertInstanceOf(Page::class, $page);
        $path = '/api/page/'.$host.'/'.$slug;
        $draftPath = '/api/pages/'.$page->id.'/draft';

        if ($present) {
            $this->request(Request::METHOD_POST, $draftPath, ['after' => 0]);
            self::assertResponseIsSuccessful();
            $draft = $this->decode();
        } else {
            $exchange = self::getContainer()->get(DraftExchange::class);
            $request = Request::create($draftPath, Request::METHOD_POST, content: '{"after":0}');
            $joined = $exchange->page($request, $page);
            self::assertSame(Response::HTTP_OK, $joined->getStatusCode());
            /** @var array{room: string} $draft */
            $draft = json_decode((string) $joined->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        }

        $this->request(Request::METHOD_GET, $path.'?return=editing');
        self::assertSame($present, $this->decode()['collaborative']);

        $patch = ['edits' => [
            ['find' => 'Start', 'replace' => 'First'],
            ['find' => 'end', 'replace' => 'last'],
        ]];
        $headers = ['HTTP_IF_MATCH' => $revision];
        if ($present) {
            $this->request(Request::METHOD_PATCH, $path, $patch + ['frontmatter' => ['h1' => 'Mixed edit']], $headers);
            self::assertResponseStatusCodeSame(Response::HTTP_LOCKED);
        }

        $this->request(Request::METHOD_PATCH, $path, ['edits' => [...$patch['edits'], ['find' => 'absent']]], $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->request(Request::METHOD_PATCH, $path.'?return=full', $patch, $headers);
        self::assertResponseIsSuccessful();
        $saved = $this->decode();
        self::assertSame('First middle last', $saved['body']);
        self::assertSame($this->testUserEmail, $saved['updatedBy']);
        self::assertSame($saved['revision'], $response->headers->get('ETag'));
        self::assertNotSame($revision, $saved['revision']);
        self::assertIsArray($saved['frontmatter']);
        self::assertSame('Test', $saved['frontmatter']['h1']);

        $this->request(Request::METHOD_PATCH, $path, $patch, $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame($saved['revision'], $this->decode()['current_revision']);

        $this->request(Request::METHOD_POST, $draftPath, ['room' => $draft['room'], 'after' => 0]);
        self::assertResponseIsSuccessful();
        $polled = $this->decode();
        self::assertSame($draft['room'], $polled['room']);
        self::assertSame(1, $polled['after']);
        self::assertIsString($polled['snapshot']);
        $projected = self::getContainer()->get(LiveProjection::class)->resolve(false, '', $polled['snapshot'], null, null);
        self::assertIsArray($projected);
        self::assertSame('First middle last', $projected['content']);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testMarkdownPatchNormalizesTheBodyOnce(bool $shared): void
    {
        [$host, $slug] = $this->createDraftWithBody('Original body');
        $path = '/api/page/'.$host.'/'.$slug;
        $id = $this->lookupPageId($host, $slug);
        $draftPath = '/api/pages/'.$id.'/draft';
        if ($shared) {
            $this->request(Request::METHOD_POST, $draftPath, ['after' => 0]);
            self::assertResponseIsSuccessful();
            $room = $this->decode()['room'];
        }

        $this->request(Request::METHOD_PATCH, $path.'?return=full', [
            'edits' => [['find' => 'Original body', 'replace' => 'New body <a id="empty"></a>']],
        ], ['HTTP_IF_MATCH' => $this->currentRevision($host, $slug)]);
        self::assertResponseIsSuccessful();
        self::assertSame('New body ', $this->decode()['body']);

        $this->request(Request::METHOD_GET, $path);
        self::assertResponseIsSuccessful();
        self::assertSame('New body ', $this->decode()['body']);

        if (isset($room)) {
            $this->request(Request::METHOD_POST, $draftPath, ['room' => $room, 'after' => 0]);
            self::assertResponseIsSuccessful();
            $snapshot = $this->decode()['snapshot'];
            self::assertIsString($snapshot);
            $projection = self::getContainer()->get(LiveProjection::class)->resolve(false, '', $snapshot, null, null);
            self::assertIsArray($projection);
            self::assertSame('New body ', $projection['content']);
        }
    }

    public function testAnAuthorOnlyChangeKeepsTheDraftOnTheCurrentRevision(): void
    {
        [$host, $slug] = $this->createDraftWithBody('Original body');
        $id = $this->lookupPageId($host, $slug);
        $draftPath = '/api/pages/'.$id.'/draft';
        $this->request(Request::METHOD_POST, $draftPath, ['after' => 0]);
        self::assertResponseIsSuccessful();
        $joined = $this->decode();
        $revision = $this->currentRevision($host, $slug);

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $other = $this->em->getRepository($userClass)->findOneBy(['email' => 'admin@example.tld']);
        self::assertInstanceOf(User::class, $other);
        self::assertNotNull($other->apiToken);
        $this->client->jsonRequest(Request::METHOD_PATCH, '/api/page/'.$host.'/'.$slug.'?return=full', [
            'edits' => [['find' => 'Original', 'replace' => 'Original']],
        ], ['HTTP_AUTHORIZATION' => 'Bearer '.$other->apiToken, 'HTTP_IF_MATCH' => $revision]);
        self::assertResponseIsSuccessful();
        self::assertSame('Original body', $this->decode()['body']);
        self::assertSame($other->email, $this->decode()['updatedBy']);

        $this->request(Request::METHOD_POST, $draftPath, ['room' => $joined['room'], 'after' => 0]);
        self::assertResponseIsSuccessful();
        $current = $this->decode();
        self::assertNotSame($joined['revision'], $current['revision']);
        self::assertSame($joined['room'], $current['room']);
    }

    #[TestWith([4_194_305])]
    #[TestWith([2_000_000])]
    public function testMarkdownPatchDoesNotWriteAnOversizedSharedDraft(int $length): void
    {
        [$host, $slug] = $this->createDraftWithBody('Original body');
        $id = $this->lookupPageId($host, $slug);
        $this->request(Request::METHOD_POST, '/api/pages/'.$id.'/draft', ['after' => 0]);
        self::assertResponseIsSuccessful();
        $revision = $this->currentRevision($host, $slug);
        $this->request(Request::METHOD_PATCH, '/api/page/'.$host.'/'.$slug, [
            'edits' => [['find' => 'Original body', 'replace' => str_repeat('x', $length)]],
        ], ['HTTP_IF_MATCH' => $revision]);
        self::assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        self::assertSame('update_too_large', $this->decode()['error']);
        self::assertSame($revision, $this->currentRevision($host, $slug));
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

        $this->request(Request::METHOD_PATCH, '/api/page/'.$host.'/'.$slug, [
            'frontmatter' => ['h1' => 'Out-of-date title'],
        ], ['HTTP_IF_MATCH' => 'stale']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
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

    public function testGetUnknownPageReturns404(): void
    {
        $response = $this->request('GET', '/api/page/nope.example.com/nothing-here');
        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteWithoutRedirectToIsRejected(): void
    {
        [$host, $slug] = $this->createTestPage();

        $response = $this->request('DELETE', '/api/page/'.$host.'/'.$slug);
        self::assertSame(400, $response->getStatusCode());

        // Nothing was deleted: the page is still readable.
        self::assertSame(200, $this->request('GET', '/api/page/'.$host.'/'.$slug)->getStatusCode());
    }

    public function testDeleteRejectsRelativeRedirectToAndSelfTarget(): void
    {
        [$host, $slug] = $this->createTestPage();

        self::assertSame(400, $this->request('DELETE', '/api/page/'.$host.'/'.$slug, ['redirectTo' => 'somewhere'])->getStatusCode());
        self::assertSame(400, $this->request('DELETE', '/api/page/'.$host.'/'.$slug, ['redirectTo' => '/'.$slug])->getStatusCode());
        self::assertSame(400, $this->request('DELETE', '/api/page/'.$host.'/'.$slug, ['redirectTo' => '/elsewhere', 'code' => 200])->getStatusCode());

        self::assertSame(200, $this->request('GET', '/api/page/'.$host.'/'.$slug)->getStatusCode());
    }

    public function testDeleteHardRemovesPageAndFoldsRedirectIntoDestination(): void
    {
        [$host, $slug] = $this->createTestPage();
        $destinationSlug = 'destination-'.uniqid();
        $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => $destinationSlug, 'h1' => 'Destination', 'locale' => 'en'],
            'body' => 'Still here',
        ]);
        $this->createdPageIds[] = $this->lookupPageId($host, $destinationSlug);

        $response = $this->request('DELETE', '/api/page/'.$host.'/'.$slug, ['redirectTo' => '/'.$destinationSlug]);
        self::assertSame(204, $response->getStatusCode());

        // Default strategy is hard delete: the row is gone, not just unpublished.
        $this->em->clear();
        self::assertNull($this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $slug]));
        self::assertSame(404, $this->request('GET', '/api/page/'.$host.'/'.$slug)->getStatusCode());

        // The old path keeps redirecting, carried by the destination page.
        $destination = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $destinationSlug]);
        self::assertInstanceOf(Page::class, $destination);
        self::assertSame([$slug => 301], $destination->redirectFrom);
    }

    public function testDeleteAcceptsRedirectToAsQueryParameter(): void
    {
        [$host, $slug] = $this->createTestPage();

        // Some clients drop the body of a DELETE, hence the query-parameter fallback.
        $response = $this->request('DELETE', '/api/page/'.$host.'/'.$slug.'?redirectTo=https://example.org/elsewhere');
        self::assertSame(204, $response->getStatusCode());

        $this->em->clear();
        $page = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $slug]);
        self::assertInstanceOf(Page::class, $page);
        self::assertSame('Location: https://example.org/elsewhere', $page->mainContent);
    }

    public function testDeleteWithUnknownInternalTargetKeepsSlugAsRedirection(): void
    {
        [$host, $slug] = $this->createTestPage();

        // No page owns the target, so nothing can carry a redirectFrom entry.
        $response = $this->request('DELETE', '/api/page/'.$host.'/'.$slug, ['redirectTo' => '/not-created-yet']);
        self::assertSame(204, $response->getStatusCode());

        $this->em->clear();
        $page = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $slug]);
        self::assertInstanceOf(Page::class, $page);
        self::assertSame('Location: /not-created-yet', $page->mainContent);
    }

    public function testDeleteDoesNotFoldIntoARedirectionTarget(): void
    {
        [$host, $slug] = $this->createTestPage();
        $redirectionSlug = 'redirection-'.uniqid();
        $this->request('POST', '/api/page/'.$host, [
            'frontmatter' => ['slug' => $redirectionSlug, 'h1' => 'Moved', 'locale' => 'en'],
            'body' => 'Location: https://example.org/final',
        ]);
        $this->createdPageIds[] = $this->lookupPageId($host, $redirectionSlug);

        $response = $this->request('DELETE', '/api/page/'.$host.'/'.$slug, ['redirectTo' => '/'.$redirectionSlug]);
        self::assertSame(204, $response->getStatusCode());

        // A redirection cannot own the old path: the deleted slug stays a redirection itself.
        $this->em->clear();
        $page = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $slug]);
        self::assertInstanceOf(Page::class, $page);
        self::assertSame('Location: /'.$redirectionSlug, $page->mainContent);

        $target = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $redirectionSlug]);
        self::assertInstanceOf(Page::class, $target);
        self::assertSame([], $target->redirectFrom);
    }

    public function testDeleteWithExternalTargetLeavesARedirectOnlyPage(): void
    {
        [$host, $slug] = $this->createTestPage();

        $response = $this->request('DELETE', '/api/page/'.$host.'/'.$slug, [
            'redirectTo' => 'https://example.org/elsewhere',
            'code' => 302,
        ]);
        self::assertSame(204, $response->getStatusCode());

        // No internal page can own the old path, so the slug survives as a redirection.
        $this->em->clear();
        $page = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $slug]);
        self::assertInstanceOf(Page::class, $page);
        self::assertSame('Location: https://example.org/elsewhere 302', $page->mainContent);
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
     * Create a draft page (no publishedAt) with a known body, returning [host, slug].
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
