<?php

namespace Pushword\Flat\Tests\Controller\Api;

use Override;
use PHPUnit\Framework\Attributes\Group;
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

    private KernelBrowser $client;

    private Filesystem $filesystem;

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
        $em->flush();

        $this->contentDir = $this->isolateContentDir();
    }

    protected function tearDown(): void
    {
        if ('' !== $this->contentDir && $this->filesystem->exists($this->contentDir)) {
            $this->filesystem->remove($this->contentDir);
        }

        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        /** @var class-string<User> $userClass */
        $userClass = $container->getParameter('pw.entity_user');
        $user = $em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $em->remove($user);
            $em->flush();
        }

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

        $entries = $this->tarballEntries($this->captureStream());
        self::assertTrue(
            $this->entriesContainSuffix($entries, 'page.md'),
            'Tarball should contain page.md, got: '.implode(', ', $entries),
        );
        self::assertTrue(
            $this->entriesContainSuffix($entries, 'blog/post.md'),
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

    private function request(string $url): Response
    {
        $this->client->request(Request::METHOD_GET, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);

        return $this->client->getResponse();
    }

    /**
     * Point the host's flat_content_dir at a fresh, isolated temp directory and
     * reset the finder cache so the controller resolves to it.
     */
    private function isolateContentDir(): string
    {
        $dir = sys_get_temp_dir().'/pushword-snapshot-test-'.getmypid().'-'.uniqid();
        $this->filesystem->mkdir($dir);

        $container = self::getContainer();

        $siteRegistry = $container->get(SiteRegistry::class);
        $siteRegistry->switchSite(self::HOST)->get()->setCustomProperty('flat_content_dir', $dir);

        $finder = $container->get(FlatFileContentDirFinder::class);
        new ReflectionProperty(FlatFileContentDirFinder::class, 'contentDir')->setValue($finder, []);

        return $dir;
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
     * @param string[] $entries
     */
    private function entriesContainSuffix(array $entries, string $suffix): bool
    {
        return array_any($entries, static fn ($entry): bool => str_ends_with($entry, $suffix));
    }
}
