<?php

namespace Pushword\StaticGenerator\Tests\Api;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class StaticApiControllerTest extends WebTestCase
{
    private const string HOST = 'localhost.dev';

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    private string $cacheDir = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Restore pristine DB so page fixtures are available for rendering:
        // other tests in the same ParaTest worker may have deleted fixture media.
        $cacheFile = getenv('PUSHWORD_TEST_DB_CACHE_FILE');
        $dbUrl = getenv('PUSHWORD_TEST_DATABASE_URL');
        if (false !== $cacheFile && '' !== $cacheFile && false !== $dbUrl && file_exists($cacheFile)) {
            $dbPath = preg_replace('#^sqlite:///+#', '/', $dbUrl);
            if (null !== $dbPath && file_exists($dbPath)) {
                copy($cacheFile, $dbPath);
            }
        }
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'static-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $this->em->persist($user);
        $this->em->flush();

        $generator = self::getContainer()->get(StaticAppGenerator::class);
        $site = self::getContainer()->get(SiteRegistry::class)->switchSite(self::HOST)->get();
        $this->cacheDir = $generator->getCacheDir($site);

        $this->cleanState();
    }

    protected function tearDown(): void
    {
        $this->cleanState();
        new Filesystem()->remove($this->cacheDir);

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = $this->em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $this->em->remove($user);
            $this->em->flush();
        }

        parent::tearDown();
    }

    public function testStatusRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/static/'.self::HOST);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testUnknownHostIsRejected(): void
    {
        $this->request('GET', '/api/static/does-not-exist.invalid');

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testStatusIsIdleWhenNeverGenerated(): void
    {
        $body = $this->request('GET', '/api/static/'.self::HOST);

        self::assertResponseIsSuccessful();
        self::assertSame(self::HOST, $body['host']);
        self::assertSame('idle', $body['status']);
        self::assertFalse($body['running']);
        self::assertSame(0, $body['errorCount']);
        self::assertSame([], $body['errors']);
    }

    public function testErrorStatusExposesConsoleOutput(): void
    {
        $this->storage()->setStatus('static-generator--'.self::HOST, 'error');
        $this->storage()->write('static-generator--'.self::HOST, 'Failed to start background process: nohup failed');

        $body = $this->request('GET', '/api/static/'.self::HOST);

        self::assertResponseIsSuccessful();
        self::assertSame('error', $body['status']);
        self::assertIsString($body['output']);
        self::assertStringContainsString('nohup failed', $body['output']);
        self::assertSame(1, $body['errorCount']);
    }

    /**
     * Under `background_task_handler: messenger` a pass can sit in the queue for
     * hours. Reported as `completed`, it was indistinguishable from one that ran —
     * same word, same unchanged `lastGeneratedAt` — so a client had nothing to
     * poll on.
     */
    public function testAQueuedPassIsNotReportedAsCompleted(): void
    {
        $this->storage()->setStatus('static-generator--'.self::HOST, 'queued');

        $body = $this->request('GET', '/api/static/'.self::HOST);

        self::assertResponseIsSuccessful();
        self::assertSame('queued', $body['status']);
        self::assertFalse($body['running']);
    }

    /**
     * The whole point of the synchronous route: the response itself is the proof
     * the file is on disk, so a remote agent never has to poll.
     */
    public function testGenerateSinglePageWritesTheFileAndReportsIt(): void
    {
        $body = $this->request('POST', '/api/static/'.self::HOST.'/homepage');

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), (string) json_encode($body));
        self::assertTrue($body['generated']);
        self::assertSame([], $body['errors']);
        self::assertIsInt($body['durationMs']);

        self::assertFileExists($this->cacheDir.'/index.html');
        self::assertStringContainsString('<html', (string) file_get_contents($this->cacheDir.'/index.html'));
    }

    public function testGenerateUnknownPageIsNotFound(): void
    {
        $this->request('POST', '/api/static/'.self::HOST.'/this-page-does-not-exist');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /**
     * A held page keeps its published file: the generator silently writes nothing,
     * so the endpoint must say so instead of reporting a success.
     */
    public function testHeldPageIsRefusedInsteadOfSilentlyDoingNothing(): void
    {
        $this->assertRefusesToGenerate(
            'publication_on_hold',
            static function (Page $page): void { $page->holdPublicationAt = new DateTime('+1 day'); },
        );
    }

    /**
     * An unpublished page has no business being exported: the whole-site pass
     * skips it, so regenerating it individually must not claim otherwise.
     */
    public function testUnpublishedPageIsRefused(): void
    {
        $this->assertRefusesToGenerate(
            'page_not_published',
            static function (Page $page): void { $page->publishedAt = new DateTime('+1 year'); },
        );
    }

    /**
     * A page opted out of the cache produces no file at all, so a success here
     * would send the caller looking for something that will never exist.
     */
    public function testPageOptedOutOfTheCacheIsRefused(): void
    {
        $this->assertRefusesToGenerate(
            'cache_disabled_for_page',
            static function (Page $page): void { $page->customProperties = ['cache' => false]; },
        );
    }

    /**
     * Build a throwaway page in the state $makeUnexportable puts it in, and assert
     * the endpoint names that state instead of writing a file.
     *
     * A throwaway rather than a fixture page on purpose: any flush made while the
     * API user is authenticated stamps the page's `editedBy`, and that user is
     * dropped in tearDown — a mutated fixture page would keep pointing at it and
     * break the next test in the class.
     *
     * @param callable(Page): void $makeUnexportable
     */
    private function assertRefusesToGenerate(string $expectedError, callable $makeUnexportable): void
    {
        $slug = 'static-api-'.uniqid();
        $page = new Page();
        $page->host = self::HOST;
        $page->slug = $slug;
        $page->h1 = 'Static API test';
        $page->publishedAt = new DateTime('-1 day');
        $makeUnexportable($page);

        $this->em->persist($page);
        $this->em->flush();

        try {
            $body = $this->request('POST', '/api/static/'.self::HOST.'/'.$slug);

            self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
            self::assertSame($expectedError, $body['error']);
            self::assertFileDoesNotExist($this->cacheDir.'/'.$slug.'.html');
        } finally {
            $this->em->remove($page);
            $this->em->flush();
        }
    }

    /**
     * A whole-site pass swaps in a freshly built directory; a page written next to
     * it would be lost, so the single-page route refuses while one is running.
     */
    public function testSinglePageIsRefusedWhileAWholeSiteGenerationRuns(): void
    {
        $this->markProcessRunning('static-generator--'.self::HOST);

        $body = $this->request('POST', '/api/static/'.self::HOST.'/homepage');

        self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
        self::assertSame('generation_running', $body['error']);
        self::assertIsString($body['statusUrl']);
        self::assertStringContainsString('/api/static/'.self::HOST, $body['statusUrl']);
    }

    public function testTriggerDoesNotStartASecondPassWhileOneRuns(): void
    {
        $this->markProcessRunning('static-generator--'.self::HOST);

        $body = $this->request('POST', '/api/static/'.self::HOST);

        self::assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());
        self::assertFalse($body['started']);
        self::assertSame('running', $body['status']);
        self::assertIsString($body['statusUrl']);
        self::assertStringContainsString('/api/static/'.self::HOST, $body['statusUrl']);
    }

    /**
     * Seed the PID file BackgroundProcessManager reads, so the lock looks taken
     * without a child process to spawn and reap. PID 1 (init/systemd) is always
     * alive and is never this process; the empty command pattern keeps the check
     * from demanding that its cmdline read `pw:static`.
     */
    private function markProcessRunning(string $processType): void
    {
        new Filesystem()->dumpFile(
            $this->varDir().'/'.$processType.'.pid',
            json_encode(['pid' => 1, 'startTime' => time(), 'commandPattern' => ''], \JSON_THROW_ON_ERROR),
        );
    }

    private function varDir(): string
    {
        return self::getContainer()->getParameter('pw.var_dir');
    }

    private function cleanState(): void
    {
        $fs = new Filesystem();
        foreach (['static-generator', 'static-generator--'.self::HOST] as $processType) {
            $fs->remove($this->varDir().'/'.$processType.'.pid');
            $this->storage()->clear($processType);
        }

        // The recorded generation time too: status() only reports "idle" while there is
        // none, and any other class generating in this ParaTest worker leaves one behind —
        // so testStatusIsIdleWhenNeverGenerated depended on which classes shared its
        // worker. Filename mirrors GenerationStateManager::STATE_FILE, which is private.
        $fs->remove($this->varDir().'/.static-generation-state.json');
    }

    private function storage(): ProcessOutputStorage
    {
        return new ProcessOutputStorage(new Filesystem(), $this->varDir());
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $url): array
    {
        $this->client->request($method, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
            'CONTENT_TYPE' => 'application/json',
        ]);

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
