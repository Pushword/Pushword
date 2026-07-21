<?php

namespace Pushword\Flat\Tests\Controller\Api;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class ContentSnapshotApiControllerTest extends WebTestCase
{
    private const string HOST = 'localhost.dev';

    /** Deterministic DB pages exported by the snapshot refresh, independent of dev-app fixtures. */
    private const string ROOT_SLUG = 'snapshot-fixture';

    private const string NESTED_SLUG = 'snapshot-fixture/leaf';

    private KernelBrowser $client;

    private Filesystem $filesystem;

    private string $baseDir = '';

    private string $contentDir = '';

    private string $testToken = '';

    private string $testUserEmail = '';

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->filesystem = new Filesystem();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'snapshot-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $em->persist($user);

        // Seed deterministic pages for the host so the snapshot's DB → flat refresh
        // exports known files (a root and a nested one), regardless of whether the
        // ambient dev-app fixtures are loaded in this worker's DB.
        $this->seedPage($em, self::ROOT_SLUG);
        $this->seedPage($em, self::NESTED_SLUG);
        $em->flush();

        $this->contentDir = $this->isolateContentDir();
    }

    private function seedPage(EntityManagerInterface $em, string $slug): void
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setH1('Snapshot fixture');
        $page->host = self::HOST;
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');
        $page->updatedAt = new DateTime('now');
        $page->setMainContent('Body of '.$slug);

        $em->persist($page);
    }

    protected function tearDown(): void
    {
        if ('' !== $this->baseDir && $this->filesystem->exists($this->baseDir)) {
            $this->filesystem->remove($this->baseDir);
        }

        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');

        $pageRepository = $em->getRepository(Page::class);
        foreach ([self::ROOT_SLUG, self::NESTED_SLUG] as $slug) {
            $page = $pageRepository->findOneBy(['slug' => $slug, 'host' => self::HOST]);
            if (null !== $page) {
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

    public function testWithoutTokenReturns401(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/content/snapshot.tar.gz?host='.self::HOST);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testInvalidTokenReturns401(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/content/snapshot.tar.gz?host='.self::HOST, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer not-a-real-token',
        ]);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testUnknownHostReturns400(): void
    {
        $response = $this->request('/api/content/snapshot.tar.gz?host=nope.invalid.example');
        self::assertSame(400, $response->getStatusCode());
    }

    public function testPathTraversalHostReturns400(): void
    {
        $response = $this->request('/api/content/snapshot.tar.gz?host=../../../etc');
        self::assertSame(400, $response->getStatusCode());
    }

    public function testEmptyContentDirReturns404(): void
    {
        // contentDir is isolated and empty (no *.md).
        $response = $this->request('/api/content/snapshot.tar.gz?host='.self::HOST);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testSnapshotReturnsGzipTarballWithMarkdown(): void
    {
        $this->populateContentDir();

        $response = $this->request('/api/content/snapshot.tar.gz?host='.self::HOST);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/gzip', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        // The refresh re-mirrors the DB, so the tarball ships the exported pages.
        $entries = $this->tarballEntries($this->captureStream());
        self::assertTrue(
            $this->entriesContainSuffix($entries, self::ROOT_SLUG.'.md'),
            'Tarball should contain the exported page, got: '.implode(', ', $entries),
        );
        self::assertTrue(
            $this->entriesContainSuffix($entries, self::NESTED_SLUG.'.md'),
            'Tarball should keep nested subdirectories',
        );
    }

    public function testSnapshotExcludesGit(): void
    {
        $this->populateContentDir();
        $this->filesystem->dumpFile($this->contentDir.'/.git/config', '[core]');

        $response = $this->request('/api/content/snapshot.tar.gz?host='.self::HOST);
        self::assertSame(200, $response->getStatusCode());

        $entries = $this->tarballEntries($this->captureStream());
        self::assertNotEmpty($entries, 'Tarball should not be empty');
        foreach ($entries as $entry) {
            self::assertStringNotContainsString('.git', $entry, 'Tarball must not contain .git');
        }
    }

    public function testSnapshotExcludesRootDotfiles(): void
    {
        $this->populateContentDir();
        $this->filesystem->dumpFile($this->contentDir.'/.env', 'SECRET=1');

        $response = $this->request('/api/content/snapshot.tar.gz?host='.self::HOST);
        self::assertSame(200, $response->getStatusCode());

        $entries = $this->tarballEntries($this->captureStream());
        self::assertTrue($this->entriesContainSuffix($entries, self::ROOT_SLUG.'.md'), 'Tarball should keep content');
        foreach ($entries as $entry) {
            self::assertStringNotContainsString('.env', $entry, 'Tarball must not contain root dotfiles');
        }
    }

    public function testSnapshotWithoutHostReturnsAllSites(): void
    {
        // With a literal (placeholder-free) flat_content_dir, the base dir
        // resolves to the same directory, so omitting host snapshots it too.
        $this->populateContentDir();

        $response = $this->request('/api/content/snapshot.tar.gz');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/gzip', $response->headers->get('Content-Type'));
        self::assertStringContainsString(
            'snapshot-all-',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function testSnapshotWithoutHostFreshensEverySite(): void
    {
        // Exercises the all-hosts branch: the dedup loop over getAll() must
        // re-export each site, so a stale localhost.dev mirror file is restamped.
        $stale = $this->contentDir.'/'.self::ROOT_SLUG.'.md';
        $this->filesystem->dumpFile($stale, "---\nslug: ".self::ROOT_SLUG."\n---\nstale body");
        $this->filesystem->touch($stale, 1);

        $response = $this->request('/api/content/snapshot.tar.gz');
        self::assertSame(200, $response->getStatusCode());

        $exported = $this->tarballEntryContent($this->captureStream(), self::ROOT_SLUG.'.md');
        self::assertStringContainsString('revision:', $exported);
        self::assertStringNotContainsString('stale body', $exported);
    }

    public function testSnapshotFreshensStaleMarkdownWithRevision(): void
    {
        // A pre-existing mirror file for the seeded DB page, missing a
        // `revision:` stamp and dated in the past so the incremental exporter
        // re-generates it instead of taking the mtime fast path.
        $stale = $this->contentDir.'/'.self::ROOT_SLUG.'.md';
        $this->filesystem->dumpFile($stale, "---\nslug: ".self::ROOT_SLUG."\n---\nstale body");
        $this->filesystem->touch($stale, 1);

        $response = $this->request('/api/content/snapshot.tar.gz?host='.self::HOST);
        self::assertSame(200, $response->getStatusCode());

        $exported = $this->tarballEntryContent($this->captureStream(), self::ROOT_SLUG.'.md');
        self::assertStringContainsString('revision:', $exported, 'Exported page must carry a fresh revision stamp');
        self::assertStringNotContainsString('stale body', $exported, 'Stale mirror content must be overwritten');
    }

    public function testSnapshotStreamsStaleMirrorWhenRefreshFails(): void
    {
        // Seed a real mirror so hasMarkdown() passes and there is content to stream.
        $this->populateContentDir();

        // Force the DB → flat refresh to throw from deep inside the exporter, in a
        // way root cannot bypass: make `index.csv` a directory, so exportIndex()'s
        // unguarded readFile()/dumpFile() raises an IOException. Without the
        // controller's try/catch this bubbles up as a 500; with it, the existing
        // mirror is streamed instead.
        $this->filesystem->mkdir($this->contentDir.'/index.csv');

        $response = $this->request('/api/content/snapshot.tar.gz?host='.self::HOST);

        self::assertSame(200, $response->getStatusCode(), 'A refresh failure must not 500 the snapshot');
        self::assertSame('application/gzip', $response->headers->get('Content-Type'));

        $entries = $this->tarballEntries($this->captureStream());
        self::assertTrue(
            $this->entriesContainSuffix($entries, self::ROOT_SLUG.'.md'),
            'The existing mirror must still be streamed despite the refresh failure',
        );
    }

    private function request(string $url): Response
    {
        $this->client->request(Request::METHOD_GET, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);

        return $this->client->getResponse();
    }

    /**
     * Give every site its OWN isolated temp directory (under one base dir) and
     * reset the finder cache so the controller resolves to it. Per-host dirs keep
     * the all-hosts export loop from writing into a shared (real or test) content
     * directory, and stop one host's orphan-prune from deleting another host's
     * freshly exported files. Returns the dir of {@see self::HOST}.
     */
    private function isolateContentDir(): string
    {
        $this->baseDir = sys_get_temp_dir().'/pushword-snapshot-test-'.getmypid().'-'.uniqid();

        $container = self::getContainer();

        $siteRegistry = $container->get(SiteRegistry::class);
        foreach ($siteRegistry->getAll() as $site) {
            $dir = $this->hostDir($site->getMainHost());
            $this->filesystem->mkdir($dir);
            $site->setCustomProperty('flat_content_dir', $dir);
        }

        $siteRegistry->switchSite(self::HOST);

        $finder = $container->get(FlatFileContentDirFinder::class);
        new ReflectionProperty(FlatFileContentDirFinder::class, 'contentDir')->setValue($finder, []);

        return $this->hostDir(self::HOST);
    }

    private function hostDir(string $host): string
    {
        return $this->baseDir.'/'.str_replace(['/', '\\', ':'], '_', $host);
    }

    private function populateContentDir(): void
    {
        $this->filesystem->dumpFile($this->contentDir.'/page.md', "---\nslug: page\n---\nHello");
        $this->filesystem->dumpFile($this->contentDir.'/blog/post.md', "---\nslug: post\n---\nPost");
    }

    /**
     * The test client already ran the StreamedResponse callback while handling
     * the request and buffered the bytes into its internal response.
     */
    private function captureStream(): string
    {
        return $this->client->getInternalResponse()->getContent();
    }

    /**
     * @return string[]
     */
    private function tarballEntries(string $gzippedTar): array
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'pw-snap-');
        $this->filesystem->dumpFile($tmp, $gzippedTar);

        $entries = [];
        exec('tar -tzf '.escapeshellarg($tmp).' 2>/dev/null', $entries);

        $this->filesystem->remove($tmp);

        return $entries;
    }

    /**
     * Extract the gzipped tarball and return the content of a root-level entry.
     */
    private function tarballEntryContent(string $gzippedTar, string $relativePath): string
    {
        $dir = sys_get_temp_dir().'/pw-snap-extract-'.getmypid().'-'.uniqid();
        $this->filesystem->mkdir($dir);
        $archive = $dir.'/archive.tar.gz';
        $this->filesystem->dumpFile($archive, $gzippedTar);

        exec('tar -xzf '.escapeshellarg($archive).' -C '.escapeshellarg($dir).' 2>/dev/null');

        $path = $dir.'/'.$relativePath;
        $content = $this->filesystem->exists($path) ? (string) file_get_contents($path) : '';

        $this->filesystem->remove($dir);

        return $content;
    }

    /**
     * @param string[] $entries
     */
    private function entriesContainSuffix(array $entries, string $suffix): bool
    {
        return array_any($entries, static fn (string $entry): bool => str_ends_with($entry, $suffix));
    }
}
