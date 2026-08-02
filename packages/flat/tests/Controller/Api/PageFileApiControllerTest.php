<?php

namespace Pushword\Flat\Tests\Controller\Api;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Serializer\PageFileSerializer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Byte-exact round-trip corpus for the raw-markdown page endpoints, built from
 * the production data-loss bugs of the hand-written editor YAML clients: a
 * `---` rule in the body, an array of maps, nesting past the inline threshold,
 * apostrophes/quotes — plus the mutation and deletion cases the unchanged
 * round-trip can never catch (a merge-semantics bug keeps them green).
 */
#[Group('integration')]
final class PageFileApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private PageFileSerializer $serializer;

    private User $apiUser;

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
        $this->serializer = self::getContainer()->get(PageFileSerializer::class);

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'page-file-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $this->em->persist($user);
        $this->em->flush();

        $this->apiUser = $user;
    }

    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        // Reverse order: children are created after their parent and must go first.
        foreach (array_reverse($this->createdPageIds) as $id) {
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

    // ----- byte-exact round trips (PUT the exported text back unchanged) -----

    public function testBodyWithHorizontalRuleRoundTripsByteExact(): void
    {
        $this->assertByteExactRoundTrip(static function (Page $page): void {
            $page->setMainContent("Intro paragraph.\n\n---\n\nEverything below the rule must survive.");
        });
    }

    public function testBodyWithTightRuleRoundTripsByteExact(): void
    {
        // `A\n---\nB` is a setext heading in markdown. The plain Spatie parser
        // split on every `---` line and re-joined with fixed padding, turning
        // the heading into a rule — the serializer's anchored parser must not.
        $this->assertByteExactRoundTrip(static function (Page $page): void {
            $page->setMainContent("Section title\n---\nBody right under the setext underline.");
        });
    }

    public function testArrayOfMapsFrontmatterRoundTripsByteExact(): void
    {
        $this->assertByteExactRoundTrip(static function (Page $page): void {
            $page->setCustomProperty('heroStats', [
                ['number' => '2,5 M', 'label' => 'lecteurs'],
                ['number' => '40+', 'label' => 'guides'],
            ]);
        });
    }

    public function testDeepNestingFrontmatterRoundTripsByteExact(): void
    {
        $this->assertByteExactRoundTrip(static function (Page $page): void {
            $page->setCustomProperty('nav', [
                'primary' => ['links' => [['label' => 'Accueil', 'path' => '/'], ['label' => 'Guides', 'path' => '/guides']]],
            ]);
        });
    }

    public function testApostrophesAndQuotesRoundTripByteExact(): void
    {
        $this->assertByteExactRoundTrip(static function (Page $page): void {
            $page->title = 'Tour de l\'Albanie : "joyaux" de l\'Île';
            $page->h1 = 'Les îles d\'Åland';
            $page->setMainContent("L'apostrophe et les \"guillemets\" restent intacts.");
        });
    }

    public function testHeldPageRoundTripsByteExact(): void
    {
        $this->assertByteExactRoundTrip(static function (Page $page): void {
            $page->holdPublicationAt = new DateTime('2025-12-01 08:00:00');
        });
    }

    /**
     * Typographic quotes are normalized server-side, so the first write moves
     * the entity onto the straightened text; from there the file is a fixed
     * point, byte for byte.
     */
    public function testCurlyQuotesConvergeInOneWrite(): void
    {
        $page = $this->persistPage(static function (Page $page): void {
            $page->title = "Tour de l\u{2019}Albanie";
            $page->setMainContent("Corps avec l\u{2019}apostrophe typographique.");
        });

        $exported = $this->serializer->serialize($page);
        self::assertStringContainsString("l'Albanie", $exported, 'export straightens the curly apostrophe');

        $first = $this->putFile($page->host, $page->slug, $exported, $this->revisionOf($exported));
        self::assertSame(Response::HTTP_OK, $first->getStatusCode());
        $firstText = (string) $first->getContent();

        $second = $this->putFile($page->host, $page->slug, $firstText, $this->revisionOf($firstText));
        self::assertSame(Response::HTTP_OK, $second->getStatusCode());
        self::assertSame($firstText, (string) $second->getContent(), 'second write must be a byte-exact fixed point');
    }

    // ----- mutations the unchanged round-trip can never catch -----

    public function testChangedH1AppliesAndRoundTrips(): void
    {
        $page = $this->persistPage(static function (Page $page): void {
            $page->h1 = 'Original heading';
        });

        $exported = $this->serializer->serialize($page);
        $edited = str_replace('Original heading', 'Changed heading', $exported);

        $response = $this->putFile($page->host, $page->slug, $edited, $this->revisionOf($exported));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Changed heading', (string) $response->getContent());

        $this->em->refresh($page);
        self::assertSame('Changed heading', $page->h1);
    }

    public function testChangedHoldPublicationAtApplies(): void
    {
        $page = $this->persistPage(static function (Page $page): void {
            $page->holdPublicationAt = new DateTime('2025-12-01 08:00:00');
        });

        $exported = $this->serializer->serialize($page);
        $edited = str_replace('2025-12-01 08:00', '2026-01-15 09:30', $exported);
        self::assertNotSame($exported, $edited, 'fixture must carry the hold date');

        $response = $this->putFile($page->host, $page->slug, $edited, $this->revisionOf($exported));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->em->refresh($page);
        self::assertSame('2026-01-15 09:30', $page->holdPublicationAt?->format('Y-m-d H:i'));
    }

    public function testDeletedCustomPropertyIsRemoved(): void
    {
        $page = $this->persistPage(static function (Page $page): void {
            $page->setCustomProperty('toc', true);
            $page->setCustomProperty('searchExcerpt', 'kept-or-not');
        });

        $exported = $this->serializer->serialize($page);
        $edited = preg_replace('/^searchExcerpt:.*\n/m', '', $exported);
        self::assertNotSame($exported, $edited, 'fixture must carry the property line');

        $response = $this->putFile($page->host, $page->slug, (string) $edited, $this->revisionOf($exported));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringNotContainsString('searchExcerpt', (string) $response->getContent());

        $this->em->refresh($page);
        self::assertFalse($page->hasCustomProperty('searchExcerpt'), 'deleting the line in the file is an edit');
        self::assertTrue($page->hasCustomProperty('toc'));
    }

    public function testDeletedParentPageLineResets(): void
    {
        $parent = $this->persistPage(static function (Page $page): void {
            $page->slug = 'parent-page';
        });

        $child = $this->persistPage(static function (Page $page) use ($parent): void {
            $page->host = $parent->host;
            $page->slug = 'child-page';
            $page->parentPage = $parent;
        });

        $exported = $this->serializer->serialize($child);
        $edited = preg_replace('/^parentPage:.*\n/m', '', $exported);
        self::assertNotSame($exported, $edited, 'fixture must carry the parentPage line');

        $response = $this->putFile($child->host, $child->slug, (string) $edited, $this->revisionOf($exported));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringNotContainsString('parentPage', (string) $response->getContent());

        $this->em->refresh($child);
        self::assertNull($child->parentPage);
    }

    public function testDeletedLocaleLineResetsToSiteLocale(): void
    {
        $siteLocale = self::getContainer()->get(SiteRegistry::class)->getDefault()->locale;
        $fixtureLocale = 'en' === $siteLocale ? 'it' : 'en';

        $page = $this->persistPage(static function (Page $page) use ($fixtureLocale): void {
            $page->locale = $fixtureLocale;
        });

        $exported = $this->serializer->serialize($page);
        self::assertStringContainsString('locale: '.$fixtureLocale, $exported);
        $edited = preg_replace('/^locale:.*\n/m', '', $exported);

        $response = $this->putFile($page->host, $page->slug, (string) $edited, $this->revisionOf($exported));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        // The site locale is the exporter's default and stays omitted.
        self::assertStringNotContainsString('locale:', (string) $response->getContent());

        $this->em->refresh($page);
        self::assertSame($siteLocale, $page->locale);
    }

    public function testDeletedMetaRobotsResets(): void
    {
        $page = $this->persistPage(static function (Page $page): void {
            $page->metaRobots = 'noindex';
        });

        $exported = $this->serializer->serialize($page);
        $edited = preg_replace('/^metaRobots:.*\n/m', '', $exported);
        self::assertNotSame($exported, $edited, 'fixture must carry the metaRobots line');

        $response = $this->putFile($page->host, $page->slug, (string) $edited, $this->revisionOf($exported));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringNotContainsString('metaRobots', (string) $response->getContent());

        $this->em->refresh($page);
        self::assertSame('', $page->metaRobots);
    }

    public function testUnparsablePublishedAtIsRejected(): void
    {
        $page = $this->persistPage(static function (Page $page): void {
            $page->publishedAt = new DateTime('2025-06-01 10:30:00');
        });

        $exported = $this->serializer->serialize($page);
        $edited = str_replace("publishedAt: '2025-06-01 10:30'", 'publishedAt: not-a-date', $exported);
        self::assertNotSame($exported, $edited, 'fixture must carry the published date');

        $response = $this->putFile($page->host, $page->slug, $edited, $this->revisionOf($exported));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('invalid_frontmatter', (string) $response->getContent());

        $this->em->refresh($page);
        self::assertSame('2025-06-01 10:30', $page->publishedAt?->format('Y-m-d H:i'), 'a typo must not unpublish');
    }

    // ----- protocol -----

    public function testMissingIfMatchIsPreconditionRequired(): void
    {
        $page = $this->persistPage(static fn (Page $page): null => null);
        $exported = $this->serializer->serialize($page);

        $response = $this->putFile($page->host, $page->slug, $exported, null);
        self::assertSame(Response::HTTP_PRECONDITION_REQUIRED, $response->getStatusCode());
    }

    public function testStaleIfMatchGetsCanonicalTextWith409(): void
    {
        $page = $this->persistPage(static function (Page $page): void {
            $page->h1 = 'Server truth';
        });
        $exported = $this->serializer->serialize($page);

        $response = $this->putFile($page->host, $page->slug, $exported, 'stale-revision');
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringStartsWith('text/markdown', (string) $response->headers->get('Content-Type'));
        self::assertSame($exported, (string) $response->getContent(), '409 body is the current canonical file text');
    }

    public function testBodyWithoutFrontmatterIsRejected(): void
    {
        $page = $this->persistPage(static fn (Page $page): null => null);
        $exported = $this->serializer->serialize($page);

        $response = $this->putFile($page->host, $page->slug, "Just a body, no front matter.\n", $this->revisionOf($exported));
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testCreateViaPostReturnsCanonicalText(): void
    {
        $host = $this->uniqueHost();
        $file = "---\nh1: 'Née par POST'\npublishedAt: '2025-06-01 10:30'\n---\n\nCorps initial.";

        $this->client->request(Request::METHOD_POST, '/api/content/page/'.$host.'/guides/nested-page', server: $this->server(), content: $file);
        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertStringStartsWith('text/markdown', (string) $response->headers->get('Content-Type'));

        $page = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => 'guides/nested-page']);
        self::assertInstanceOf(Page::class, $page);
        $this->createdPageIds[] = $page->id ?? 0;

        self::assertSame('Née par POST', $page->h1);
        self::assertSame($this->serializer->serialize($page), (string) $response->getContent());
        self::assertNotNull($this->revisionOf((string) $response->getContent()), 'the response carries the new revision');
    }

    public function testCreateOnExistingSlugGetsCanonicalTextWith409(): void
    {
        $page = $this->persistPage(static fn (Page $page): null => null);

        $this->client->request(Request::METHOD_POST, '/api/content/page/'.$page->host.'/'.$page->slug, server: $this->server(), content: "---\nh1: Doublon\n---\n\nx");
        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame($this->serializer->serialize($page), (string) $response->getContent());
    }

    public function testEditorSyncScriptIsServed(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/editor/sync.js', server: $this->server());
        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringStartsWith('text/javascript', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('PUSHWORD_API_BASE', (string) $response->getContent());
        self::assertStringNotContainsString('parseYaml', (string) $response->getContent(), 'the shipped client holds no YAML');
    }

    // ----- helpers -----

    /**
     * @param callable(Page): mixed $configure
     */
    private function assertByteExactRoundTrip(callable $configure): void
    {
        $page = $this->persistPage($configure);
        $exported = $this->serializer->serialize($page);

        $response = $this->putFile($page->host, $page->slug, $exported, $this->revisionOf($exported));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringStartsWith('text/markdown', (string) $response->headers->get('Content-Type'));
        self::assertSame($exported, (string) $response->getContent(), 'PUT of the exported text must return it byte for byte');
        self::assertSame($this->revisionOf($exported), $response->headers->get('ETag'));
    }

    /**
     * @param callable(Page): mixed $configure
     */
    private function persistPage(callable $configure): Page
    {
        $page = new Page();
        $page->host = $this->uniqueHost();
        $page->slug = 'corpus-page';
        $page->h1 = 'Corpus fixture';
        $page->publishedAt = new DateTime('2025-06-01 10:30:00');
        $page->setMainContent('Default body.');
        // Pre-stamp the API user: the write re-stamps the same entity, so an
        // unchanged PUT produces an empty changeset and a stable revision.
        $page->editedBy = $this->apiUser;
        $page->createdBy = $this->apiUser;

        $configure($page);

        $this->em->persist($page);
        $this->em->flush();
        $this->createdPageIds[] = $page->id ?? 0;

        return $page;
    }

    private function putFile(string $host, string $slug, string $content, ?string $ifMatch): Response
    {
        $server = $this->server();
        if (null !== $ifMatch) {
            $server['HTTP_IF_MATCH'] = $ifMatch;
        }

        $this->client->request(Request::METHOD_PUT, '/api/content/page/'.$host.'/'.$slug, server: $server, content: $content);

        return $this->client->getResponse();
    }

    /**
     * @return array<string, string>
     */
    private function server(): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
            'CONTENT_TYPE' => 'text/markdown',
        ];
    }

    private function revisionOf(string $fileText): ?string
    {
        return 1 === preg_match('/^revision: (\S+)/m', $fileText, $matches) ? $matches[1] : null;
    }

    private function uniqueHost(): string
    {
        return 'file-api-'.uniqid().'.example.com';
    }
}
