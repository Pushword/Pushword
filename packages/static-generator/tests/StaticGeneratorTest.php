<?php

namespace Pushword\StaticGenerator;

use DateTime;
use DateTimeImmutable;
use Exception;
use FilesystemIterator;
use Iterator;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Pushword\Core\Cache\RenderEpoch;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\MediaCacheStorageAdapter;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Event\StaticPostGenerateEvent;
use Pushword\StaticGenerator\Event\StaticPreGenerateEvent;
use Pushword\StaticGenerator\Generator\AbstractGenerator;
use Pushword\StaticGenerator\Generator\CaddyfileGenerator;
use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CompressionAlgorithm;
use Pushword\StaticGenerator\Generator\Compressor;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\HtaccessGenerator;
use Pushword\StaticGenerator\Generator\HtmlMinifier;
use Pushword\StaticGenerator\Generator\MediaGenerator;
use Pushword\StaticGenerator\Generator\PageGenerator;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionHtmlGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ReflectionProperty;

use function Safe\realpath;

use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Group('integration')]
final class StaticGeneratorTest extends KernelTestCase
{
    private ?StaticAppGenerator $staticAppGenerator = null;

    private ?string $isolatedStaticDir = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Restore pristine DB: other tests in the same ParaTest worker may have
        // deleted fixture media, causing page rendering to fail on missing media.
        $cacheFile = getenv('PUSHWORD_TEST_DB_CACHE_FILE');
        $dbUrl = getenv('PUSHWORD_TEST_DATABASE_URL');
        if (false !== $cacheFile && '' !== $cacheFile && false !== $dbUrl && file_exists($cacheFile)) {
            $dbPath = preg_replace('#^sqlite:///+#', '/', $dbUrl);
            if (null !== $dbPath && file_exists($dbPath)) {
                copy($cacheFile, $dbPath);
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Use a per-process temp dir to avoid cross-worker interference
        $this->isolatedStaticDir = sys_get_temp_dir().'/pushword-static-test-'.getmypid();
    }

    protected function tearDown(): void
    {
        if (null !== $this->isolatedStaticDir) {
            new Filesystem()->remove($this->isolatedStaticDir);
        }

        parent::tearDown();
    }

    private function overrideStaticDir(): void
    {
        $container = self::getContainer();
        $siteRegistry = $container->get(SiteRegistry::class);
        $siteConfig = $siteRegistry->switchSite('localhost.dev')->get();
        $siteConfig->setCustomProperty('static_dir', $this->isolatedStaticDir);
        // Force classic static mode (pushword.yaml sets `cache: static` for localhost.dev,
        // which would redirect output into the public cache dir instead of the static dir).
        $siteConfig->setCustomProperty('cache', 'none');

        // Clean up any leftover PID files in the per-worker var dir
        $varDir = (string) getenv('PUSHWORD_TEST_VAR_DIR');
        if ('' !== $varDir) {
            new Filesystem()->remove(glob($varDir.'/static-generator*.pid') ?: []);
        }
    }

    private function getStaticDir(): string
    {
        return $this->isolatedStaticDir ?? throw new LogicException('isolatedStaticDir not set');
    }

    private function getStateFilePath(): string
    {
        // Mirror GenerationStateManager::getStateFilePath(): tests isolate the
        // state file per ParaTest worker via PUSHWORD_TEST_VAR_DIR.
        $testVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        if (false !== $testVarDir && '' !== $testVarDir) {
            return $testVarDir.'/.static-generation-state.json';
        }

        return self::getContainer()->getParameter('kernel.project_dir').'/var/.static-generation-state.json';
    }

    public function testStaticCommand(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        $application = new Application(self::$kernel); // @phpstan-ignore-line

        $command = $application->find('pw:static');
        $commandTester = new CommandTester($command);

        // No --workers: the one test that runs the command exactly as a user does, so the
        // default fan-out (WorkerCountResolver::resolve(0, …)) is exercised end to end.
        // Everywhere else in this class asks for a worker count explicitly — auto resolves
        // to one subprocess per page, and 16 kernel boots per generation cost more CPU than
        // the rest of the class put together.
        $commandTester->execute(['host' => 'localhost.dev', '--format' => 'text']);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('success', $output, 'Static generation failed. Output: '.$output);
        self::assertStringNotContainsString('<error>', $output, 'Static generation had errors. Output: '.$output);

        $staticDir = $this->getStaticDir();
        self::assertFileExists($staticDir.'/.htaccess');
        self::assertFileExists($staticDir.'/.Caddyfile');
        self::assertFileExists($staticDir.'/index.html');
        self::assertFileExists($staticDir.'/index.html.zst');
        self::assertFileExists($staticDir.'/index.html.br');
        self::assertFileExists($staticDir.'/index.html.gz');
        self::assertFileExists($staticDir.'/robots.txt');
        self::assertFileExists($staticDir.'/favicon.ico');
    }

    public function testStaticCommandAgentOutputIsJson(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        $application = new Application(self::$kernel); // @phpstan-ignore-line

        $command = $application->find('pw:static');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1, '--format' => 'agent']);

        $output = trim($commandTester->getDisplay());

        // No human noise leaks into agent output.
        self::assertStringNotContainsString('PID', $output);
        self::assertStringNotContainsString('peak memory', $output);
        self::assertStringNotContainsString('success', $output);

        $decoded = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('pw:static', $decoded['tool']);
        self::assertContains($decoded['result'], ['passed', 'failed']);
        self::assertArrayHasKey('errors_count', $decoded);
        self::assertArrayHasKey('errors', $decoded);
        self::assertArrayHasKey('duration_ms', $decoded);
    }

    public function testIncrementalGeneration(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $staticDir = $this->getStaticDir();
        $stateFile = $this->getStateFilePath();

        $command = $application->find('pw:static');
        $commandTester = new CommandTester($command);

        // First full generation
        $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1, '--format' => 'text']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('success', $output);
        self::assertStringNotContainsString('incremental', $output);

        // State file should be created
        self::assertFileExists($stateFile);
        $stateContent = file_get_contents($stateFile);
        self::assertNotFalse($stateContent);
        self::assertStringContainsString('localhost.dev', $stateContent);

        // Get modification time of index.html
        $indexFile = $staticDir.'/index.html';
        self::assertFileExists($indexFile);
        // Set file mtime ahead so any regeneration produces a different (current-time) mtime
        touch($indexFile, time() + 2);
        clearstatcache();
        $originalMtime = filemtime($indexFile);

        // Reboot the kernel so the incremental run starts from a clean container,
        // exactly like the separate `pw:static --incremental` process it simulates.
        // Without this, transient render state cached on shared entities (e.g. a
        // Media's soft alt set while rendering another page) leaks from the full run
        // into the incremental render and produces non-deterministic HTML.
        $commandTester = $this->rebootStaticCommandTester();

        // Second generation with incremental flag
        $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1, '--incremental' => true, '--format' => 'text']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('success', $output);
        self::assertStringContainsString('incremental mode', $output);

        // Index.html should NOT be regenerated (same mtime since page unchanged)
        clearstatcache();
        $newMtime = filemtime($indexFile);
        self::assertSame($originalMtime, $newMtime, 'File should not be regenerated in incremental mode when unchanged');
    }

    /**
     * Reboot the kernel and return a fresh CommandTester for `pw:static`, keeping
     * the per-process static dir override in place. Used by incremental tests so
     * the second run mirrors a real, separate command invocation rather than
     * reusing the first run's in-memory container state.
     */
    private function rebootStaticCommandTester(): CommandTester
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $this->overrideStaticDir();

        $application = new Application(self::$kernel); // @phpstan-ignore-line

        return new CommandTester($application->find('pw:static'));
    }

    public function testIncrementalPrunesDeletedAndUnpublishedPages(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $staticDir = $this->getStaticDir();

        $doomed = $this->makeProbePage('prune-deleted-probe');
        $retired = $this->makeProbePage('prune-unpublished-probe');
        $em->persist($doomed);
        $em->persist($retired);
        $em->flush();

        try {
            // Workers=1 keeps the build in-process so it reads the just-committed
            // rows deterministically (see testHeldPageKeepsPublishedVersionAcrossFullRebuild).
            $commandTester = new CommandTester(new Application(self::$kernel)->find('pw:static')); // @phpstan-ignore-line
            $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1, '--format' => 'text']);

            self::assertFileExists($staticDir.'/prune-deleted-probe.html');
            self::assertFileExists($staticDir.'/prune-deleted-probe.html.gz');
            self::assertFileExists($staticDir.'/prune-unpublished-probe.html');

            $em->remove($doomed);
            $retired->publishedAt = null;
            $em->flush();

            $commandTester = $this->rebootStaticCommandTester();
            $commandTester->execute(['host' => 'localhost.dev', '--incremental' => true, '--workers' => 1, '--format' => 'text']);

            $output = $commandTester->getDisplay();
            self::assertStringContainsString('Removed localhost.dev/prune-deleted-probe', $output);
            self::assertStringContainsString('Removed localhost.dev/prune-unpublished-probe', $output);

            // The in-place incremental build must sweep the vanished pages' files
            // (the full build gets this for free from its atomic dir swap)...
            self::assertFileDoesNotExist($staticDir.'/prune-deleted-probe.html');
            self::assertFileDoesNotExist($staticDir.'/prune-deleted-probe.html.gz');
            self::assertFileDoesNotExist($staticDir.'/prune-unpublished-probe.html');
            // ...without touching anything still published.
            self::assertFileExists($staticDir.'/index.html');
        } finally {
            // Restore pristine state for the shared worker DB.
            $resetEm = self::getContainer()->get('doctrine.orm.default_entity_manager');
            $pageRepository = self::getContainer()->get(PageRepository::class);
            foreach (['prune-deleted-probe', 'prune-unpublished-probe'] as $slug) {
                $leftover = $pageRepository->findOneBy(['host' => 'localhost.dev', 'slug' => $slug]);
                if (null !== $leftover) {
                    $resetEm->remove($leftover);
                }
            }

            $resetEm->flush();
        }
    }

    private function makeProbePage(string $slug): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = $slug;
        $page->title = 'Prune probe '.$slug;
        $page->h1 = 'Prune probe';
        $page->mainContent = 'Content destined for pruning.';
        $page->publishedAt = new DateTime('-1 hour');

        return $page;
    }

    public function testHeldPageKeepsPublishedVersionAcrossFullRebuild(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        $container = self::getContainer();
        $pageRepository = $container->get(PageRepository::class);
        $em = $container->get('doctrine.orm.default_entity_manager');

        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $staticDir = $this->getStaticDir();
        $commandTester = new CommandTester($application->find('pw:static'));

        // First full generation produces index.html for the homepage.
        $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1]);

        $indexFile = $staticDir.'/index.html';
        self::assertFileExists($indexFile);
        $publishedContent = (string) file_get_contents($indexFile);

        $homepage = $pageRepository->findOneBy(['host' => 'localhost.dev', 'slug' => 'homepage']);
        self::assertNotNull($homepage);
        $originalH1 = $homepage->h1;

        try {
            // Edit the page AND hold it: the live DB changes but production must
            // keep serving the previously published version across a full rebuild.
            $homepage->setHoldPublication(true);
            $homepage->h1 = 'Held edit '.uniqid();
            $em->flush();

            $commandTester = $this->rebootStaticCommandTester();
            // workers=1 keeps the rebuild in-process, so the held check reads this
            // test's just-committed holdPublicationAt directly. With parallel workers
            // the check runs in freshly-spawned child processes whose DB read can race
            // the commit under load, intermittently missing the hold and regenerating
            // the page. That race is a test-harness timing artefact — production commits
            // content long before pw:static runs — so it must not gate the carry-over
            // behaviour under test here. Parallel generation mechanics are exercised by
            // the testParallelGeneration* tests; the worker's held-skip (generateSlugs)
            // mirrors the in-process guard this test drives.
            $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1, '--format' => 'text']);

            self::assertStringContainsString('Held', $commandTester->getDisplay());

            clearstatcache();
            self::assertFileExists($indexFile, 'Held page must survive a full (temp + swap) rebuild');
            self::assertFileExists($indexFile.'.gz', 'Held page compressed sidecars must be carried over');
            self::assertSame(
                $publishedContent,
                (string) file_get_contents($indexFile),
                'Held page must keep serving its previously published version',
            );
        } finally {
            // Restore pristine state for the shared worker DB.
            $resetEm = self::getContainer()->get('doctrine.orm.default_entity_manager');
            $reloaded = self::getContainer()->get(PageRepository::class)
                ->findOneBy(['host' => 'localhost.dev', 'slug' => 'homepage']);
            if (null !== $reloaded) {
                $reloaded->setHoldPublication(false);
                $reloaded->h1 = $originalH1;
                $resetEm->flush();
            }
        }
    }

    public function testGenerationStateManager(): void
    {
        // Use isolated temp dir for state file. Unset PUSHWORD_TEST_VAR_DIR so
        // GenerationStateManager honours the constructor projectDir under test
        // (it otherwise redirects the state file to the per-worker var dir).
        $previousVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        putenv('PUSHWORD_TEST_VAR_DIR');

        $tempDir = sys_get_temp_dir().'/pushword-state-test-'.getmypid();
        new Filesystem()->mkdir($tempDir.'/var');
        $stateFile = $tempDir.'/var/.static-generation-state.json';

        try {
            $stateManager = new GenerationStateManager($tempDir);

            // Initially no state
            self::assertFalse($stateManager->hasState('test.host'));
            self::assertNull($stateManager->getLastGenerationTime('test.host'));

            // Set generation time
            $now = new DateTimeImmutable();
            $stateManager->setLastGenerationTime('test.host', $now);
            $stateManager->save();

            // Verify state file created
            self::assertFileExists($stateFile);

            // Create new instance to verify persistence
            $stateManager2 = new GenerationStateManager($tempDir);
            self::assertTrue($stateManager2->hasState('test.host'));
            self::assertNotNull($stateManager2->getLastGenerationTime('test.host'));

            // Test page state
            $pageUpdatedAt = new DateTimeImmutable('2024-01-15 10:00:00');
            $stateManager2->setPageState('test.host', 'test-page', $pageUpdatedAt, 'epoch-1');
            $stateManager2->save();

            // Verify page doesn't need regeneration with same timestamp and epoch
            self::assertFalse($stateManager2->needsRegeneration('test.host', 'test-page', $pageUpdatedAt, 'epoch-1'));

            // Verify page needs regeneration with different timestamp
            $newUpdatedAt = new DateTimeImmutable('2024-01-16 10:00:00');
            self::assertTrue($stateManager2->needsRegeneration('test.host', 'test-page', $newUpdatedAt, 'epoch-1'));

            // A bumped epoch makes the page stale even with an unchanged timestamp
            self::assertTrue($stateManager2->needsRegeneration('test.host', 'test-page', $pageUpdatedAt, 'epoch-2'));

            // Swept epoch is the sampled value recorded on success, absent until then
            self::assertNull($stateManager2->getSweptEpoch('test.host'));
            $stateManager2->setSweptEpoch('test.host', 'epoch-1');
            $stateManager2->save();
            self::assertSame('epoch-1', new GenerationStateManager($tempDir)->getSweptEpoch('test.host'));
        } finally {
            new Filesystem()->remove($tempDir);
            putenv(false === $previousVarDir ? 'PUSHWORD_TEST_VAR_DIR' : 'PUSHWORD_TEST_VAR_DIR='.$previousVarDir);
        }
    }

    private function getStaticAppGenerator(): StaticAppGenerator
    {
        if (null !== $this->staticAppGenerator) {
            return $this->staticAppGenerator;
        }

        $generatorBag = $this->getGeneratorBag();

        $container = self::getContainer();
        $logger = $container->get(LoggerInterface::class);

        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        return $this->staticAppGenerator = new StaticAppGenerator(
            self::getContainer()->get(SiteRegistry::class),
            $generatorBag,
            $generatorBag->get(RedirectionManager::class), // @phpstan-ignore-line
            $logger,
            new GenerationStateManager($projectDir),
            self::getContainer()->get(RenderEpoch::class),
            self::getContainer()->get(EventDispatcherInterface::class),
            self::getContainer()->get(PageRepository::class),
            $projectDir,
            self::getContainer()->getParameter('kernel.environment'),
            self::getContainer()->getParameter('pw.var_dir'),
        );
    }

    public function testIt(): void
    {
        $generator = $this->getStaticAppGenerator();
        $this->overrideStaticDir();

        $generator->generate('localhost.dev');

        $staticDir = $this->getStaticDir();
        self::assertFileExists($staticDir);

        // A static host never proxies /admin, so exported HTML must not embed the
        // admin-toolbar live block: with a pw_auth cookie it would dead-POST the
        // unreachable fragment endpoint on every page view (page_default.html.twig
        // skips the admin_buttons block when isStatic is true).
        $indexFile = $staticDir.'/index.html';
        self::assertFileExists($indexFile);
        self::assertStringNotContainsString(
            'admin/fragment/page-buttons',
            (string) file_get_contents($indexFile),
        );
    }

    /**
     * A build writes no media, so it must not advance the media version: a bump
     * here invalidates every image-bearing markdown fragment on every build.
     * Media writes bump the version themselves (MediaCacheInvalidationListener).
     */
    public function testBuildLeavesTheMediaVersionUntouched(): void
    {
        $generator = $this->getStaticAppGenerator();
        $this->overrideStaticDir();

        $mediaRepository = AbstractGenerator::getKernel()->getContainer()->get(MediaRepository::class);
        $readVersion = new ReflectionMethod(MediaRepository::class, 'readVersion');
        $before = $readVersion->invoke($mediaRepository);

        $generator->generate('localhost.dev');

        self::assertSame($before, $readVersion->invoke($mediaRepository));
    }

    private function getGenerator(string $name): GeneratorInterface
    {
        return $this->getGeneratorBag()->get($name)->setStaticAppGenerator($this->getStaticAppGenerator());
    }

    public function testGenerateHtaccess(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $generator = $this->getGenerator(HtaccessGenerator::class);

        $generator->generate('localhost.dev');

        $htaccess = (string) file_get_contents($this->getStaticDir().'/.htaccess');
        self::assertFileExists($this->getStaticDir().'/.htaccess');
        self::assertStringContainsString('max-age=10800', $htaccess);
        self::assertStringContainsString('stale-while-revalidate=3600', $htaccess);
        self::assertStringContainsString('ErrorDocument 404 /404', $htaccess);

        // localhost.dev is en|fr: the fr folder overrides the root ErrorDocument
        // so /fr/… errors serve the localized 404 dumped by ErrorPageGenerator.
        // Exact match: any mod_rewrite directive sneaking in here would stop the
        // root rewrite rules from being inherited under /fr/.
        self::assertSame(
            'ErrorDocument 403 /fr/404'.\PHP_EOL.'ErrorDocument 404 /fr/404'.\PHP_EOL.'ErrorDocument 500 /fr/404'.\PHP_EOL,
            (string) file_get_contents($this->getStaticDir().'/fr/.htaccess')
        );
    }

    public function testLocaleErrorRoutingSkippedOnSingleLocaleSite(): void
    {
        self::bootKernel();

        // admin-block-editor.test keeps the default single locale: no scoped
        // error routing must be emitted at all.
        $siteRegistry = self::getContainer()->get(SiteRegistry::class);
        $siteRegistry->switchSite('admin-block-editor.test')->get()
            ->setCustomProperty('static_dir', $this->getStaticDir());

        $this->getGenerator(HtaccessGenerator::class)->generate('admin-block-editor.test');
        $this->getGenerator(CaddyfileGenerator::class)->generate('admin-block-editor.test');

        self::assertFileExists($this->getStaticDir().'/.htaccess');
        self::assertFileDoesNotExist($this->getStaticDir().'/en/.htaccess');

        $caddyfile = (string) file_get_contents($this->getStaticDir().'/.Caddyfile');
        self::assertStringNotContainsString('@error_', $caddyfile);
        self::assertStringContainsString('rewrite * /404.html', $caddyfile);
    }

    public function testGenerateCaddyfile(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $generator = $this->getGenerator(CaddyfileGenerator::class);

        $generator->generate('localhost.dev');

        $caddyfile = (string) file_get_contents($this->getStaticDir().'/.Caddyfile');
        self::assertFileExists($this->getStaticDir().'/.Caddyfile');
        self::assertStringContainsString('max-age=10800', $caddyfile);
        self::assertStringContainsString('stale-while-revalidate=3600', $caddyfile);
        self::assertStringContainsString('rewrite * /fr/404.html', $caddyfile);
        self::assertStringContainsString('rewrite * /404.html', $caddyfile);
    }

    /**
     * Caddy evaluates the matchers of one named set in non-deterministic order (a Go
     * map walk, caddyserver/caddy#6904), so a `file` submatcher reading {re.webp.1}
     * from its path_regexp sibling probes garbage (/media/.jpg) on the config loads
     * where it evaluates first, and the fallback goes silently inert. The
     * capture-dependent probing must live in a directive guarded by the matcher —
     * directives run after the whole set has matched, when the capture is reliably
     * set — and a missing original must keep the URI intact for handle_errors.
     */
    public function testCaddyfileWebpFallbackProbesOriginalsOutsideTheMatcherSet(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $this->getGenerator(CaddyfileGenerator::class)->generate('localhost.dev');

        $caddyfile = (string) file_get_contents($this->getStaticDir().'/.Caddyfile');

        // The named matcher uses only the request's own placeholders, never a sibling's capture.
        self::assertStringContainsString(
            "\t@webp_fallback {\n\t\tpath_regexp webp ^/media/(.+)\\.webp\$\n\t\tnot file {path}\n\t}\n",
            $caddyfile,
        );
        // The probing runs as a try_files directive, ending with {path} so a genuine
        // miss keeps its .webp URI instead of rewriting to a mangled one.
        self::assertStringContainsString(
            "\troute @webp_fallback {\n\t\ttry_files /media/{re.webp.1}.jpg /media/{re.webp.1}.jpeg /media/{re.webp.1}.png /media/{re.webp.1}.gif {path}\n\t}\n",
            $caddyfile,
        );
        self::assertStringNotContainsString(
            'rewrite @webp_fallback',
            $caddyfile,
            'A rewrite reading {http.matchers.file.relative} implies the file submatcher shape this test pins out.',
        );
        self::assertStringNotContainsString(
            '{http.request.uri.path_regexp',
            $caddyfile,
            'The extension-stripping placeholder is an older regression this test also pins down.',
        );
    }

    public function testGenerateRedirectionHtml(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $siteRegistry = self::getContainer()->get(SiteRegistry::class);
        $locale = $siteRegistry->switchSite('localhost.dev')->get()->locale;

        /** @var RedirectionManager $redirectionManager */
        $redirectionManager = $this->getGeneratorBag()->get(RedirectionManager::class);
        $redirectionManager->reset();
        $redirectionManager->add('/cms-comparison', '/blog/cms-comparison', 301);

        $this->getGenerator(RedirectionHtmlGenerator::class)->generate('localhost.dev');

        $stub = $this->getStaticDir().'/cms-comparison.html';
        self::assertFileExists($stub);

        $html = (string) file_get_contents($stub);
        self::assertStringContainsString('<link rel="canonical" href="/blog/cms-comparison">', $html);
        self::assertStringContainsString('content="0; url=/blog/cms-comparison"', $html);
        self::assertStringContainsString('<html lang="'.$locale.'">', $html);
    }

    public function testRedirectFromFeedsRedirectionManager(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev');

        /** @var RedirectionManager $redirectionManager */
        $redirectionManager = $this->getGeneratorBag()->get(RedirectionManager::class);
        $redirectionManager->reset();

        $page = new Page(false);
        $page->host = 'localhost.dev';
        $page->slug = 'redirect-dest-test';
        $page->mainContent = 'content';
        $page->redirectFrom = ['old-incoming' => 308];

        $redirectionManager->addRedirectFrom($page);

        $redirections = $redirectionManager->get();
        self::assertCount(1, $redirections);
        [$from, $to, $code] = $redirections[0];
        self::assertStringContainsString('old-incoming', $from);
        self::assertStringContainsString('redirect-dest-test', $to);
        self::assertSame(308, $code);
    }

    /**
     * The static redirect map is served from the host root, so its "from"/"to"
     * paths must never carry a /{host}/ prefix — even on a non-default host. This
     * host-less output is what AbstractGenerator's setUseCustomHostPath(false) aims
     * to guarantee (belt-and-suspenders: PushwordRouteGenerator::mayUseCustomPath()
     * also drops the prefix here because RedirectionManager generates with no host
     * argument and no current page).
     */
    public function testRedirectFromPathsAreHostLessOnNonDefaultHost(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        self::getContainer()->get(SiteRegistry::class)->switchSite('pushword.piedweb.com');

        /** @var RedirectionManager $redirectionManager */
        $redirectionManager = $this->getGeneratorBag()->get(RedirectionManager::class);
        $redirectionManager->reset();

        $page = new Page(false);
        $page->host = 'pushword.piedweb.com';
        $page->slug = 'redirect-dest-test';
        $page->mainContent = 'content';
        $page->redirectFrom = ['old-incoming' => 308];

        $redirectionManager->addRedirectFrom($page);

        [$from, $to] = $redirectionManager->get()[0];
        self::assertStringStartsWith('/old-incoming', $from);
        self::assertStringNotContainsString('pushword.piedweb.com', $from);
        self::assertStringNotContainsString('pushword.piedweb.com', $to);
    }

    /**
     * Regression guard for the parallel/worker static-generation link bug.
     *
     * A brand static host is served from its own root, so every internal link a
     * rendered page carries must be host-less (`/slug`, never `/{host}/slug`). The
     * /{host}/ prefix is produced by PushwordRouteGenerator when useCustomHostPath is
     * on; AbstractGenerator turns it off once, in its constructor. But the generator
     * implements ResetInterface, so under a long-lived worker the framework's
     * services_resetter runs reset() between page renders and flips it back on. A
     * worker renders many pages, so only the *first* stayed host-less — every later
     * page re-prefixed its links with /{host}/ and 404'd on the live static host.
     * saveAsStatic must therefore re-assert the flag before *every* render.
     *
     * In production the worker resets the very kernel that renders the page; here the
     * generator renders through its own sub-kernel (AbstractGenerator::$appKernel), so
     * we reset that kernel's services_resetter — the faithful boundary. Without the
     * per-render re-assert the probe page renders href="/pushword.piedweb.com/installation".
     */
    public function testWorkerResetBetweenRendersKeepsLinksHostLess(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');

        // The render sub-kernel (KernelTrait::$appKernel) is a process-wide singleton
        // that accumulates state across tests. Start from a fresh one so this test's
        // worker-reset simulation is deterministic whether run alone or in the suite.
        AbstractGenerator::$appKernel = null;

        // A real, persisted page on a NON-default host: localhost.dev is the default
        // host (prefix suppressed regardless of the flag), so persist a real
        // pushword.piedweb.com page.
        $probe = new Page(false);
        $probe->host = 'pushword.piedweb.com';
        $probe->slug = 'worker-reset-probe';
        $probe->locale = 'en';
        $probe->h1 = 'Worker reset probe';
        $probe->mainContent = 'Probe body.';
        $probe->createdAt = new DateTime('2 days ago');

        $em->persist($probe);
        $em->flush();

        try {
            // Constructor sets useCustomHostPath=false on the render kernel's router.
            $generator = $this->getGenerator(PagesGenerator::class);
            self::assertInstanceOf(PageGenerator::class, $generator);

            // The exact boundary a long-lived worker crosses between page renders:
            // services_resetter runs PushwordRouteGenerator::reset() on the SAME kernel
            // that renders the HTML (getGenerator() just booted it), flipping the
            // /{host}/ prefix back on.
            AbstractGenerator::getKernel()->getContainer()->get('services_resetter')->reset();

            $container->get(SiteRegistry::class)->switchSite('pushword.piedweb.com');
            new ReflectionMethod(AbstractGenerator::class, 'init')->invoke($generator, 'pushword.piedweb.com');
            $page = $container->get(PageRepository::class)
                ->findOneBy(['host' => 'pushword.piedweb.com', 'slug' => 'worker-reset-probe']);
            self::assertNotNull($page);

            // Render the SAME page twice. The second render is the one that
            // crosses the real boundary: the first handle() marks services for
            // reset, so the second handle() runs the services_resetter inside its
            // boot() — after anything saveAsStatic could set before the call.
            // Without the pinned flag, only the first render stays host-less.
            $saveAsStatic = new ReflectionMethod(PageGenerator::class, 'saveAsStatic');
            foreach (['first', 'second'] as $render) {
                $destination = $this->getStaticDir().'/worker-reset-regression-'.$render.'.html';
                $saveAsStatic->invoke($generator, $generator->generateLivePathFor($page), $destination, $page);

                self::assertFileExists(
                    $destination,
                    $render.' render must be 200 (errors: '.implode(' | ', $this->getStaticAppGenerator()->getErrors()).')',
                );
                $html = (string) file_get_contents($destination);

                // The bug's fingerprint: a root-relative link that begins with the host
                // segment — href="/pushword.piedweb.com/…". Distinct from the legitimate
                // absolute canonical href="https://pushword.piedweb.com/…".
                self::assertStringNotContainsString(
                    'href="/pushword.piedweb.com',
                    $html,
                    $render.' render: internal links must not carry the /{host}/ prefix on a static host',
                );
                // Non-vacuity: a page()-built nav link rendered host-less.
                self::assertStringContainsString('href="/installation"', $html, $render.' render');
            }
        } finally {
            $resetEm = self::getContainer()->get('doctrine.orm.default_entity_manager');
            $planted = self::getContainer()->get(PageRepository::class)
                ->findOneBy(['host' => 'pushword.piedweb.com', 'slug' => 'worker-reset-probe']);
            if (null !== $planted) {
                $resetEm->remove($planted);
                $resetEm->flush();
            }
        }
    }

    /**
     * StaticOutputLinter end-to-end: any emitted href whose first path segment is
     * a configured host (href="/pushword.piedweb.com/…") must fail the run — no
     * matter what produced it (route-prefix regressions, stale cached fragments,
     * hand-written content links). The failure must reach the exit code (cron
     * visibility) and abort the atomic swap so the last good export keeps serving.
     */
    public function testLintRefusesToPublishHostPrefixedLinks(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        $staticDir = $this->getStaticDir();

        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:static'));

        // Clean full generation: publishes and exits 0.
        $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1, '--format' => 'text']);
        self::assertSame(0, $commandTester->getStatusCode(), $commandTester->getDisplay());
        self::assertFileExists($staticDir.'/index.html');

        // A markdown link renders its href verbatim (raw HTML would be stripped
        // by CommonMark's default html_input).
        $probe = new Page(); // default ctor sets publishedAt — getPublishedPages() must pick it up
        $probe->host = 'localhost.dev';
        $probe->slug = 'lint-probe';
        $probe->locale = 'en';
        $probe->h1 = 'Lint probe';
        $probe->mainContent = '[poisoned](/pushword.piedweb.com/installation)';
        $probe->publishedAt = new DateTime('2 days ago');

        $em->persist($probe);
        $em->flush();

        try {
            $commandTester = $this->rebootStaticCommandTester();
            $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1, '--format' => 'text']);

            self::assertSame(1, $commandTester->getStatusCode(), 'lint errors must reach the exit code');
            self::assertStringContainsString('Host-prefixed internal link', $commandTester->getDisplay());

            clearstatcache();
            self::assertFileDoesNotExist(
                $staticDir.'/lint-probe.html',
                'a poisoned export must never replace the published one (swap aborted)',
            );
            self::assertFileExists($staticDir.'/index.html', 'the last good export must keep serving');
        } finally {
            new Filesystem()->remove($staticDir.'~'); // aborted swap leaves the temp dir

            $resetEm = self::getContainer()->get('doctrine.orm.default_entity_manager');
            $planted = self::getContainer()->get(PageRepository::class)
                ->findOneBy(['host' => 'localhost.dev', 'slug' => 'lint-probe']);
            if (null !== $planted) {
                $resetEm->remove($planted);
                $resetEm->flush();
            }
        }
    }

    public function testGenerateCNAME(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $generator = $this->getGenerator(CNAMEGenerator::class);

        $generator->generate('localhost.dev');

        self::assertFileExists($this->getStaticDir().'/CNAME');
    }

    public function testCopier(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $generator = $this->getGenerator(CopierGenerator::class);

        $generator->generate('localhost.dev');

        self::assertFileExists($this->getStaticDir().'/assets');
    }

    public function testError(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $generator = $this->getGenerator(ErrorPageGenerator::class);

        $generator->generate('localhost.dev');

        self::assertFileExists($this->getStaticDir().'/404.html');

        // One 404 per extra locale, rendered with that locale's translations;
        // the root one stays in the default locale.
        $localized = (string) file_get_contents($this->getStaticDir().'/fr/404.html');
        self::assertStringContainsString('Une erreur', $localized);
        $root = (string) file_get_contents($this->getStaticDir().'/404.html');
        self::assertStringContainsString('An error', $root);

        // The error page is the only render the generator runs on the kernel that
        // serves live traffic, so it is the only one that flips isStatic there.
        // It must hand the shared SiteConfig objects back untouched: under a
        // FrankenPHP worker they outlive the request, and every later one would
        // render as if it were being exported.
        /** @var SiteRegistry $registry */
        $registry = self::getContainer()->get(SiteRegistry::class);
        foreach ($registry->getAll() as $host => $site) {
            self::assertFalse($site->isStatic, $host.' must not stay flagged as a static export');
        }
    }

    public function testGetDebugKernelIsAlwaysDebug(): void
    {
        self::bootKernel();
        $this->getGenerator(PagesGenerator::class); // ensures loadKernel was called

        self::assertTrue(AbstractGenerator::getDebugKernel()->isDebug());
    }

    public function testFiveHundredResponseUsesDebugKernelForDetail(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        // Get generator first so the real kernel is loaded into static properties
        $generator = $this->getGenerator(PagesGenerator::class);

        $mainKernel = self::createStub(KernelInterface::class);
        $mainKernel->method('isDebug')->willReturn(false);
        $mainKernel->method('handle')->willReturn(new Response('', Response::HTTP_INTERNAL_SERVER_ERROR));

        $debugKernel = self::createStub(KernelInterface::class);
        $debugKernel->method('handle')->willReturn(
            new Response('<html><body><p>Twig error: variable not found</p></body></html>', Response::HTTP_INTERNAL_SERVER_ERROR),
        );

        $originalAppKernel = AbstractGenerator::$appKernel;
        $debugKernelProp = new ReflectionProperty(AbstractGenerator::class, 'debugKernel');
        $originalDebugKernel = $debugKernelProp->getValue();

        AbstractGenerator::$appKernel = $mainKernel;
        $debugKernelProp->setValue(null, $debugKernel);

        try {
            new ReflectionMethod(AbstractGenerator::class, 'init')->invoke($generator, 'localhost.dev');
            new ReflectionMethod(PageGenerator::class, 'saveAsStatic')
                ->invoke($generator, '/test-500', $this->getStaticDir().'/test-500.html', null);
        } finally {
            AbstractGenerator::$appKernel = $originalAppKernel;
            $debugKernelProp->setValue(null, $originalDebugKernel);
        }

        $errors = $this->getStaticAppGenerator()->getErrors();
        self::assertCount(1, $errors);
        self::assertStringContainsString('status code 500', $errors[0]);
        self::assertStringContainsString('Twig error: variable not found', $errors[0]);
    }

    #[Group('serial')]
    public function testDownload(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $generator = $this->getGenerator(MediaGenerator::class);

        $generator->generate('localhost.dev');

        $mediaDir = $this->getStaticDir().'/media';
        self::assertFileExists($mediaDir);

        // Verify media files are readable (not broken symlinks)
        $this->assertMediaFilesAccessible($mediaDir);
    }

    public function testDownloadCopiesRemoteMediaCache(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $storage = new Flysystem(new InMemoryFilesystemAdapter());
        $storage->write('md/remote-only.webp', 'remote-variant');

        $generator = $this->getGenerator(MediaGenerator::class);
        self::assertInstanceOf(MediaGenerator::class, $generator);
        $defaultCache = self::getContainer()->get(MediaCacheStorageAdapter::class);
        $generator->setMediaCacheStorage(new MediaCacheStorageAdapter($storage, isLocal: false));

        try {
            $generator->generate('localhost.dev');

            self::assertSame('remote-variant', file_get_contents($this->getStaticDir().'/media/md/remote-only.webp'));
        } finally {
            $generator->setMediaCacheStorage($defaultCache);
        }
    }

    /**
     * Both image writers put their throwaway next to the file they produce, so a
     * build running while any image is optimized or encoded meets one. Copying it
     * either publishes a truncated image or — because the file is renamed away as
     * soon as the writer is done — kills the build with a half-finished copy. Each
     * naming form the media tree can hold is seeded here: the optimizer's, the
     * encoder's, and the writer-less one the encoder wrote before rc865.
     */
    #[Group('serial')]
    #[DataProvider('mediaBuildModeProvider')]
    public function testDownloadIgnoresImageScratchFiles(bool $incremental): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $generator = $this->getGenerator(MediaGenerator::class);
        self::assertInstanceOf(MediaGenerator::class, $generator);

        // An incremental run only reaches its own copy path once the target exists;
        // a first full build is what puts it there.
        if ($incremental) {
            $generator->generate('localhost.dev');
            $generator->setIncremental(true);
        }

        // Seed the trees the build actually walks: derivatives under
        // pw.media_cache_dir, masters under pw.media_dir. Seeding public/media —
        // where derivatives lived until they were isolated per worker — plants the
        // files where nothing looks, which is how this test stopped proving anything.
        $mediaCacheDir = self::getContainer()->getParameter('pw.media_cache_dir');
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $tmpFiles = [];
        foreach ([$mediaCacheDir.'/md', $mediaDir] as $sourceDir) {
            foreach ([
                'optimizer-race.webp.opt-4242.abcdef123456.tmp',
                'encoder-race.webp.enc-4242.abcdef123456.tmp',
                'encoder-legacy.webp.4242.abcdef123456.tmp',
            ] as $name) {
                $tmpFiles[] = $sourceDir.'/'.$name;
            }
        }

        foreach ($tmpFiles as $tmpFile) {
            if (! is_dir(\dirname($tmpFile))) {
                mkdir(\dirname($tmpFile), 0o777, true);
            }

            file_put_contents($tmpFile, 'half-written');
        }

        try {
            $generator->generate('localhost.dev');

            $staticMediaDir = $this->getStaticDir().'/media';
            self::assertFileExists($staticMediaDir);

            foreach (glob($staticMediaDir.'/{,*/}*.tmp', \GLOB_BRACE) ?: [] as $leaked) {
                self::fail('An image scratch file reached the build: '.$leaked);
            }
        } finally {
            $generator->setIncremental(false);
            foreach ($tmpFiles as $tmpFile) {
                if (is_file($tmpFile)) {
                    unlink($tmpFile);
                }
            }
        }
    }

    /**
     * @return Iterator<string, array{bool}>
     */
    public static function mediaBuildModeProvider(): Iterator
    {
        // mirror() copies the whole directory; the incremental path walks it file by
        // file instead, so each has to drop the throwaway on its own.
        yield 'full build' => [false];
        yield 'incremental build' => [true];
    }

    #[Group('serial')]
    public function testDownloadWithSymlink(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $container = self::getContainer();
        $siteRegistry = $container->get(SiteRegistry::class);
        $siteConfig = $siteRegistry->switchSite('localhost.dev')->get();
        $siteConfig->setCustomProperty('static_symlink', true);

        try {
            $generator = $this->getGenerator(MediaGenerator::class);
            $generator->generate('localhost.dev');

            $mediaDir = $this->getStaticDir().'/media';
            self::assertFileExists($mediaDir);

            $this->assertMediaFilesAccessible($mediaDir);
            $this->assertSymlinksAreRelative($mediaDir);
        } finally {
            $siteConfig->setCustomProperty('static_symlink', false);
        }
    }

    public function testSelectiveSymlinkMediaOnly(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $container = self::getContainer();
        $siteRegistry = $container->get(SiteRegistry::class);
        $siteConfig = $siteRegistry->switchSite('localhost.dev')->get();
        $siteConfig->setCustomProperty('static_symlink', ['media']);

        try {
            // Media should be symlinked
            $mediaGenerator = $this->getGenerator(MediaGenerator::class);
            $mediaGenerator->generate('localhost.dev');

            $mediaDir = $this->getStaticDir().'/media';
            self::assertFileExists($mediaDir);
            $this->assertMediaFilesAccessible($mediaDir);
            $this->assertContainsSymlinks($mediaDir);
            $this->assertSymlinksAreRelative($mediaDir);

            // Assets should be copied (not symlinked)
            $copierGenerator = $this->getGenerator(CopierGenerator::class);
            $copierGenerator->generate('localhost.dev');

            $assetsDir = $this->getStaticDir().'/assets';
            self::assertFileExists($assetsDir);
            self::assertFalse(is_link($assetsDir), 'Assets should be copied, not symlinked, when static_symlink is [media]');
        } finally {
            $siteConfig->setCustomProperty('static_symlink', false);
        }
    }

    public function testSelectiveSymlinkAssetsOnly(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $container = self::getContainer();
        $siteRegistry = $container->get(SiteRegistry::class);
        $siteConfig = $siteRegistry->switchSite('localhost.dev')->get();
        $siteConfig->setCustomProperty('static_symlink', ['assets']);

        try {
            // Media should be copied (not symlinked)
            $mediaGenerator = $this->getGenerator(MediaGenerator::class);
            $mediaGenerator->generate('localhost.dev');

            $mediaDir = $this->getStaticDir().'/media';
            self::assertFileExists($mediaDir);
            $this->assertMediaFilesAccessible($mediaDir);
            $this->assertContainsNoSymlinks($mediaDir);

            // Assets should be symlinked
            $copierGenerator = $this->getGenerator(CopierGenerator::class);
            $copierGenerator->generate('localhost.dev');

            $assetsDir = $this->getStaticDir().'/assets';
            self::assertFileExists($assetsDir);
            self::assertTrue(is_link($assetsDir), 'Assets should be symlinked when static_symlink is [assets]');
        } finally {
            $siteConfig->setCustomProperty('static_symlink', false);
        }
    }

    private function assertMediaFilesAccessible(string $dir): void
    {
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = $file->getPathname();
            if (is_link($path)) {
                self::assertFileExists($path, \sprintf('Broken symlink: %s -> %s', $path, (string) readlink($path)));
            }
        }
    }

    private function assertContainsSymlinks(string $dir): void
    {
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (is_link($file->getPathname())) {
                return;
            }
        }

        self::fail('Expected at least one symlink in '.$dir);
    }

    private function assertContainsNoSymlinks(string $dir): void
    {
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = $file->getPathname();
            self::assertFalse(is_link($path), \sprintf('Unexpected symlink: %s', $path));
        }
    }

    private function assertSymlinksAreRelative(string $dir): void
    {
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = $file->getPathname();
            if (is_link($path)) {
                $target = readlink($path);
                self::assertNotFalse($target);
                self::assertStringStartsNotWith('/', $target, \sprintf('Absolute symlink found: %s -> %s', $path, $target));
            }
        }
    }

    public function testPages(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $generator = $this->getGenerator(PagesGenerator::class);

        $generator->generate('localhost.dev');

        self::assertFileExists($this->getStaticDir().'/index.html');
    }

    public function testEventsAreDispatched(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $preEvents = [];
        $postEvents = [];

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(StaticPreGenerateEvent::class, static function (StaticPreGenerateEvent $event) use (&$preEvents): void {
            $preEvents[] = $event;
        });
        $dispatcher->addListener(StaticPostGenerateEvent::class, static function (StaticPostGenerateEvent $event) use (&$postEvents): void {
            $postEvents[] = $event;
        });

        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $command = $application->find('pw:static');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1]);

        self::assertCount(1, $preEvents, 'StaticPreGenerateEvent should be dispatched once per host');
        self::assertCount(1, $postEvents, 'StaticPostGenerateEvent should be dispatched once per host');
        self::assertSame('localhost.dev', $preEvents[0]->app->getMainHost());
        self::assertSame([], $postEvents[0]->errors);
        self::assertFalse($preEvents[0]->incremental);
    }

    public function testGeneratorBagResolvesAllBuiltinGenerators(): void
    {
        self::bootKernel();
        $bag = $this->getGeneratorBag();

        $generatorClasses = [
            PagesGenerator::class,
            CopierGenerator::class,
            MediaGenerator::class,
            HtaccessGenerator::class,
            ErrorPageGenerator::class,
            CNAMEGenerator::class,
            RedirectionManager::class,
        ];

        foreach ($generatorClasses as $generatorClass) {
            $generator = $bag->get($generatorClass);
            self::assertSame($generator::class, $generatorClass);
        }
    }

    public function testGeneratorBagThrowsOnUnknownGenerator(): void
    {
        self::bootKernel();
        $bag = $this->getGeneratorBag();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/not registered/');
        $bag->get('App\\NonExistent\\Generator');
    }

    public function testStaticAssetsCleanRemovesStaleFiles(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $container = self::getContainer();
        $siteRegistry = $container->get(SiteRegistry::class);
        $siteConfig = $siteRegistry->switchSite('localhost.dev')->get();
        $siteConfig->setCustomProperty('static_symlink', false);
        $siteConfig->setCustomProperty('static_assets_clean', true);

        try {
            $staticDir = $this->getStaticDir();
            $filesystem = new Filesystem();

            // First generation to create assets dir
            $generator = $this->getGenerator(CopierGenerator::class);
            $generator->generate('localhost.dev');
            self::assertFileExists($staticDir.'/assets');

            // Plant a stale file that doesn't exist in public/assets
            $staleFile = $staticDir.'/assets/old-hash-abc123.js';
            $filesystem->dumpFile($staleFile, 'stale content');
            self::assertFileExists($staleFile);

            // Regenerate with clean enabled — stale file should be gone
            $generator->generate('localhost.dev');
            self::assertFileDoesNotExist($staleFile, 'Stale file should be removed when static_assets_clean is true');
            self::assertFileExists($staticDir.'/assets');
        } finally {
            $siteConfig->setCustomProperty('static_symlink', false);
            $siteConfig->setCustomProperty('static_assets_clean', false);
        }
    }

    public function testStaleTempDirCleanup(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $staticDir = $this->getStaticDir();
        $filesystem = new Filesystem();

        // Create a stale temp dir (older than 1 hour)
        $staleTempDir = $staticDir.'~';
        $filesystem->mkdir($staleTempDir);
        touch($staleTempDir, time() - 7200);

        $staleBackupDir = $staticDir.'~~';
        $filesystem->mkdir($staleBackupDir);
        touch($staleBackupDir, time() - 7200);

        self::assertDirectoryExists($staleTempDir);
        self::assertDirectoryExists($staleBackupDir);

        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $command = $application->find('pw:static');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1]);

        self::assertDirectoryDoesNotExist($staleTempDir, 'Stale temp dir should be cleaned up');
        self::assertDirectoryDoesNotExist($staleBackupDir, 'Stale backup dir should be cleaned up');
    }

    /**
     * The temp dir must not survive the atomic swap on the SiteConfig. The other
     * multi-run tests reboot the kernel between generations (they simulate separate
     * processes), so only an in-process second run catches the leak: it would take
     * `<dir>~` for the real static dir and leave `<dir>` frozen on the first export.
     */
    public function testStaticDirIsRestoredAfterTheAtomicSwap(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $staticDir = $this->getStaticDir();
        $siteConfig = self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev')->get();
        $staticAppGenerator = self::getContainer()->get(StaticAppGenerator::class);

        $staticAppGenerator->generate('localhost.dev');
        self::assertSame($staticDir, $siteConfig->getStr('static_dir'), 'the temp dir must not leak into the SiteConfig');

        // Drop the first export before regenerating: without this the file left by
        // the run above would satisfy the assertion even when the second generation
        // published into `<dir>~` instead.
        new Filesystem()->remove($staticDir.'/index.html');
        $staticAppGenerator->generate('localhost.dev');
        self::assertFileExists($staticDir.'/index.html');
    }

    public function testNativeGzipCompression(): void
    {
        $compressor = new Compressor();

        // Gzip should be available natively via zlib
        self::assertTrue(CompressionAlgorithm::Gzip->hasNativeSupport());

        $content = str_repeat('Hello World! This is test content for compression. ', 100);
        $compressed = CompressionAlgorithm::Gzip->nativeCompress($content);
        self::assertNotNull($compressed);
        self::assertLessThan(\strlen($content), \strlen($compressed));

        // Verify decompression produces original content
        $decompressed = gzdecode($compressed);
        self::assertIsString($decompressed);
        self::assertSame($content, $decompressed);
    }

    public function testNativeCompressionFallbackForMissingExtensions(): void
    {
        // Brotli and zstd are not installed as PHP extensions
        // nativeCompress should return null, not crash
        if (! \function_exists('brotli_compress')) {
            self::assertNull(CompressionAlgorithm::Brotli->nativeCompress('test'));
            self::assertFalse(CompressionAlgorithm::Brotli->hasNativeSupport());
        }

        if (! \function_exists('zstd_compress')) {
            self::assertNull(CompressionAlgorithm::Zstd->nativeCompress('test'));
            self::assertFalse(CompressionAlgorithm::Zstd->hasNativeSupport());
        }
    }

    public function testCompressorUsesNativeGzipInsteadOfProcess(): void
    {
        $tempFile = sys_get_temp_dir().'/pushword-compress-test-'.getmypid().'.html';
        file_put_contents($tempFile, '<html><body>Test content for native compression</body></html>');

        try {
            $compressor = new Compressor();
            $compressor->compress($tempFile, CompressionAlgorithm::Gzip);

            // Native compression is synchronous — file should exist immediately
            // (no need to call waitForCompressionToFinish for native)
            self::assertFileExists($tempFile.'.gz');

            $gzContent = file_get_contents($tempFile.'.gz');
            self::assertIsString($gzContent);
            $decompressed = gzdecode($gzContent);
            self::assertIsString($decompressed);
            self::assertSame(file_get_contents($tempFile), $decompressed);
        } finally {
            @unlink($tempFile);
            @unlink($tempFile.'.gz');
        }
    }

    public function testPreloadedPageSkipsDbQuery(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $generator = $this->getGenerator(PagesGenerator::class);
        $generator->generate('localhost.dev');

        // The static dir should have index.html — this proves the page was rendered
        // successfully using the pre-loaded page entity
        self::assertFileExists($this->getStaticDir().'/index.html');

        $content = file_get_contents($this->getStaticDir().'/index.html');
        self::assertNotEmpty($content);
        self::assertStringContainsString('Pushword', $content);
    }

    public function testContentUnchangedSkipsRewriteInIncremental(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $command = $application->find('pw:static');
        $commandTester = new CommandTester($command);

        // First run (full)
        $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1]);

        $indexFile = $this->getStaticDir().'/index.html';
        self::assertFileExists($indexFile);

        // Set mtime to a known value
        touch($indexFile, time() + 100);
        clearstatcache();
        $originalMtime = filemtime($indexFile);

        // Reboot so the incremental run mirrors a real, separate command invocation
        // (avoids transient render state leaking from the full run; see
        // rebootStaticCommandTester).
        $commandTester = $this->rebootStaticCommandTester();

        // Incremental run — content is unchanged, file should NOT be rewritten
        $commandTester->execute(['host' => 'localhost.dev', '--workers' => 1, '--incremental' => true]);

        clearstatcache();
        $newMtime = filemtime($indexFile);
        self::assertSame($originalMtime, $newMtime, 'File should not be rewritten when content is unchanged in incremental mode');
    }

    public function testWorkerCountResolverExplicitOverride(): void
    {
        self::assertSame(3, WorkerCountResolver::resolve(3, 100));
        self::assertSame(5, WorkerCountResolver::resolve(10, 5)); // capped by page count
    }

    public function testWorkerCountResolverSmallPageCount(): void
    {
        self::assertSame(1, WorkerCountResolver::resolve(0, 5));
        self::assertSame(1, WorkerCountResolver::resolve(0, 9));
    }

    public function testWorkerCountResolverAutoDetectsAboveThreshold(): void
    {
        $workers = WorkerCountResolver::resolve(0, 1000);
        self::assertGreaterThan(1, $workers);
    }

    #[Group('serial')]
    public function testParallelGenerationProducesSameOutput(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $siteRegistry = $container->get(SiteRegistry::class);

        // Sequential run
        $seqDir = sys_get_temp_dir().'/pushword-static-seq-'.getmypid();
        $siteConfig = $siteRegistry->switchSite('localhost.dev')->get();
        $siteConfig->setCustomProperty('static_dir', $seqDir);
        $siteConfig->setCustomProperty('cache', 'none');
        $this->cleanupPidFiles();

        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $command = $application->find('pw:static');
        $tester = new CommandTester($command);
        $tester->execute(['host' => 'localhost.dev', '--workers' => 1, '--format' => 'text']);

        $seqOutput = $tester->getDisplay();
        self::assertStringContainsString('success', $seqOutput, 'Sequential generation failed: '.$seqOutput);

        // Parallel run
        $parDir = sys_get_temp_dir().'/pushword-static-par-'.getmypid();
        $siteConfig->setCustomProperty('static_dir', $parDir);
        $this->cleanupPidFiles();

        $tester->execute(['host' => 'localhost.dev', '--workers' => 2, '--format' => 'text']);
        $parOutput = $tester->getDisplay();
        self::assertStringContainsString('success', $parOutput, 'Parallel generation failed: '.$parOutput);

        // Compare HTML file list (same pages generated)
        $seqFiles = $this->getHtmlFiles($seqDir);
        $parFiles = $this->getHtmlFiles($parDir);

        self::assertSame(array_keys($seqFiles), array_keys($parFiles), 'Parallel should produce same files as sequential');

        // Verify all parallel files have non-empty content
        foreach ($parFiles as $relativePath => $content) {
            self::assertNotEmpty($content, 'Empty content for '.$relativePath);
        }

        new Filesystem()->remove([$seqDir, $parDir]);
    }

    /**
     * Workers stamp state entries through their own path (generateSlugs → worker
     * state file → parent merge), separate from the sequential loop. If they
     * stamped a wrong or missing epoch, every page would read as stale on the
     * next incremental run and the "Skipped" branch would never be taken.
     */
    #[Group('serial')]
    public function testIncrementalAfterParallelBuildSkipsUnchangedPages(): void
    {
        self::bootKernel();

        $parDir = sys_get_temp_dir().'/pushword-static-par-incr-'.getmypid();
        $siteConfig = self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev')->get();
        $siteConfig->setCustomProperty('static_dir', $parDir);
        $siteConfig->setCustomProperty('cache', 'none');
        $this->cleanupPidFiles();

        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $tester = new CommandTester($application->find('pw:static'));

        try {
            $tester->execute(['host' => 'localhost.dev', '--workers' => 2, '--format' => 'text']);
            self::assertStringContainsString('success', $tester->getDisplay(), 'Parallel build failed: '.$tester->getDisplay());

            $this->cleanupPidFiles();
            // Stays parallel: this is the one place an incremental run reads state that
            // parallel workers wrote and merged.
            $tester->execute(['host' => 'localhost.dev', '--workers' => 2, '--incremental' => true, '--format' => 'text']);

            $output = $tester->getDisplay();
            self::assertStringContainsString('success', $output, 'Incremental run failed: '.$output);
            // Not homepage/pushword: fixtures carrying redirections are always
            // re-processed to rebuild the redirection map, epoch or not.
            self::assertStringContainsString('Skipped localhost.dev/kitchen-sink (unchanged)', $output);
        } finally {
            new Filesystem()->remove($parDir);
        }
    }

    #[Group('serial')]
    public function testParallelGenerationShowsWorkerPrefix(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        $this->cleanupPidFiles();

        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $command = $application->find('pw:static');
        $tester = new CommandTester($command);
        $tester->execute(['host' => 'localhost.dev', '--workers' => 2, '--format' => 'text']);

        $output = $tester->getDisplay();
        self::assertStringContainsString('[W0]', $output);
        self::assertStringContainsString('workers', $output);
        self::assertStringContainsString('success', $output);
    }

    /**
     * A worker killed by a signal writes nothing before dying, so its exit code
     * is everything the parent gets — reported as a bare number, every crash read
     * `failed (exit 139):` with nothing after the colon. `exit(139)` stands in for
     * the segfault here: Symfony reports a signaled child as 128 + the signal, so
     * a real SIGSEGV reaches this code as that same exit code and empty stderr.
     */
    public function testAFailedWorkerIsReportedWithWhatKilledItAndItsStderr(): void
    {
        self::bootKernel();
        $staticAppGenerator = self::getContainer()->get(StaticAppGenerator::class);

        $output = new BufferedOutput();
        $staticAppGenerator->setOutput($output);

        // A worker that dies leaves no state files behind, as here.
        $missing = sys_get_temp_dir().'/pushword-no-such-worker-file-'.getmypid().'.json';

        $workers = [];
        foreach ([0 => 'exit(139);', 1 => 'fwrite(STDERR, "PHP Fatal error: boom\n"); exit(255);'] as $i => $code) {
            $process = new Process(['php', '-r', $code]);
            $process->start();
            $workers[$i] = ['process' => $process, 'stateFile' => $missing, 'redirectionsFile' => $missing];
        }

        new ReflectionMethod(StaticAppGenerator::class, 'waitForWorkers')->invoke($staticAppGenerator, $workers);

        $errors = $staticAppGenerator->getErrors();
        self::assertCount(2, $errors);
        self::assertSame('Worker 0 failed (exit 139: Segmentation violation): no error output', $errors[0]);
        self::assertStringContainsString('Worker 1 failed (exit 255:', $errors[1]);
        self::assertStringContainsString('PHP Fatal error: boom', $errors[1]);

        // Stderr also reaches the operator while the build runs, not only after it.
        self::assertStringContainsString('[W1] PHP Fatal error: boom', $output->fetch());
    }

    public function testStateMergeFromFile(): void
    {
        $tempDir = sys_get_temp_dir().'/pushword-state-merge-'.getmypid();
        new Filesystem()->mkdir($tempDir.'/var');

        try {
            $stateManager = new GenerationStateManager($tempDir);
            $stateManager->setLastGenerationTime('test.host');

            // Create a worker state file — page-b simulates a legacy entry written
            // before the epoch existed: it must read as stale whatever the epoch.
            $workerFile = $tempDir.'/var/.worker-0.json';
            file_put_contents($workerFile, json_encode([
                'test.host' => [
                    'pages' => [
                        'page-a' => ['generatedAt' => '2025-01-01T00:00:00+00:00', 'pageUpdatedAt' => '2025-01-01T00:00:00+00:00', 'epoch' => 'epoch-1'],
                        'page-b' => ['generatedAt' => '2025-01-01T00:00:00+00:00', 'pageUpdatedAt' => '2025-01-01T00:00:00+00:00'],
                    ],
                ],
            ]));

            $stateManager->mergeFromFile($workerFile);

            self::assertFalse($stateManager->needsRegeneration('test.host', 'page-a', new DateTimeImmutable('2025-01-01T00:00:00+00:00'), 'epoch-1'));
            self::assertTrue($stateManager->needsRegeneration('test.host', 'page-b', new DateTimeImmutable('2025-01-01T00:00:00+00:00'), 'epoch-1'));
            self::assertFileDoesNotExist($workerFile, 'Worker file should be cleaned up after merge');
        } finally {
            new Filesystem()->remove($tempDir);
        }
    }

    public function testRedirectionExportImport(): void
    {
        self::bootKernel();

        /** @var RedirectionManager $manager */
        $manager = $this->getGeneratorBag()->get(RedirectionManager::class);
        $manager->reset();
        $manager->add('/old', '/new', 301);
        $manager->add('/legacy', '/modern', 302);

        $tempFile = sys_get_temp_dir().'/pushword-redir-'.getmypid().'.json';

        try {
            $manager->exportToFile($tempFile);
            self::assertFileExists($tempFile);

            // Reset and import
            $manager->reset();
            self::assertSame([], $manager->get());

            $manager->importFromFile($tempFile);
            self::assertCount(2, $manager->get());
            self::assertSame('/old', $manager->get()[0][0]);
            self::assertSame('/new', $manager->get()[0][1]);
            self::assertFileDoesNotExist($tempFile, 'Import should clean up the file');
        } finally {
            @unlink($tempFile);
        }
    }

    private function cleanupPidFiles(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $filesystem = new Filesystem();
        foreach (glob($projectDir.'/var/static-generator*.pid') ?: [] as $pid) {
            $filesystem->remove($pid);
        }
    }

    /**
     * @return array<string, string> relativePath => content
     */
    private function getHtmlFiles(string $dir): array
    {
        $files = [];
        $prefixLen = \strlen($dir) + 1;

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ('html' !== $file->getExtension()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), $prefixLen);
            $files[$relativePath] = (string) file_get_contents($file->getPathname());
        }

        ksort($files);

        return $files;
    }

    public function getGeneratorBag(): GeneratorBag
    {
        return self::getContainer()->get(GeneratorBag::class);
    }

    public function getParameterBag(): MockObject
    {
        $params = $this->createMock(ParameterBagInterface::class);

        $params->method('get')
             ->willReturnCallback(self::getParams(...));

        return $params;
    }

    public static function getParams(string $name): string
    {
        if ('kernel.project_dir' === $name) {
            return __DIR__.'/../../dev-app';
        }

        if ('pw.public_media_dir' === $name) {
            return 'media';
        }

        if ('pw.media_dir' === $name) {
            return realpath(__DIR__.'/../../dev-app/media');
        }

        if ('pw.public_dir' === $name) {
            return realpath(__DIR__.'/../../dev-app/public');
        }

        throw new Exception();
    }

    /**
     * HtmlMinifier's skip counter is process-wide, so hosts sharing a process
     * would each report their predecessors' pages if the notice did not take a
     * delta. Reported counts drive the "upgrade libxml" hint; an inflated one
     * would blame a healthy host for its neighbour's pages.
     */
    public function testMinificationNoticeReportsOnlyItsOwnPages(): void
    {
        $generator = $this->getGenerator(PagesGenerator::class);
        $notice = new ReflectionMethod(PageGenerator::class, 'minificationSkippedNotice');
        $reported = new ReflectionProperty(PageGenerator::class, 'minificationSkippedReported');

        $skipped = HtmlMinifier::$skippedOnBrokenLibxml;
        $reported->setValue($generator, 0);
        HtmlMinifier::$skippedOnBrokenLibxml = 0;

        try {
            self::assertNull($notice->invoke($generator), 'nothing skipped yet');

            HtmlMinifier::$skippedOnBrokenLibxml = 3;
            self::assertStringContainsString('3 page(s)', (string) $notice->invoke($generator));

            self::assertNull($notice->invoke($generator), 'a reported count must not be reported twice');

            HtmlMinifier::$skippedOnBrokenLibxml = 5;
            self::assertStringContainsString(
                '2 page(s)',
                (string) $notice->invoke($generator),
                'the next host reports its own delta, not the running total',
            );
        } finally {
            HtmlMinifier::$skippedOnBrokenLibxml = $skipped;
            $reported->setValue($generator, 0);
        }
    }

    public function getPageRepo(): MockObject
    {
        $page = new Page();
        $page->h1 = 'Welcome to Pushword !';
        $page->slug = 'homepage';
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');
        $page->mainContent = '...';

        $pageRepo = $this->createMock(PageRepository::class);
        $pageRepo->method('getPublishedPages')
                  ->willReturn([
                      $page,
                  ]);

        return $pageRepo;
    }
}
