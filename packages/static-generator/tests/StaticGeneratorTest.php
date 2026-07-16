<?php

namespace Pushword\StaticGenerator;

use DateTime;
use DateTimeImmutable;
use Exception;
use FilesystemIterator;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
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

        $commandTester->execute(['host' => 'localhost.dev', '--format' => 'agent']);

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
        $commandTester->execute(['host' => 'localhost.dev', '--format' => 'text']);

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
        $commandTester->execute(['host' => 'localhost.dev', '--incremental' => true, '--format' => 'text']);

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
        $commandTester->execute(['host' => 'localhost.dev']);

        $indexFile = $staticDir.'/index.html';
        self::assertFileExists($indexFile);
        $publishedContent = (string) file_get_contents($indexFile);

        $homepage = $pageRepository->findOneBy(['host' => 'localhost.dev', 'slug' => 'homepage']);
        self::assertNotNull($homepage);
        $originalH1 = $homepage->getH1();

        try {
            // Edit the page AND hold it: the live DB changes but production must
            // keep serving the previously published version across a full rebuild.
            $homepage->setHoldPublication(true);
            $homepage->setH1('Held edit '.uniqid());
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
                $reloaded->setH1($originalH1);
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
            $stateManager2->setPageState('test.host', 'test-page', $pageUpdatedAt);
            $stateManager2->save();

            // Verify page doesn't need regeneration with same timestamp
            self::assertFalse($stateManager2->needsRegeneration('test.host', 'test-page', $pageUpdatedAt));

            // Verify page needs regeneration with different timestamp
            $newUpdatedAt = new DateTimeImmutable('2024-01-16 10:00:00');
            self::assertTrue($stateManager2->needsRegeneration('test.host', 'test-page', $newUpdatedAt));
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
            self::getContainer()->get(EventDispatcherInterface::class),
            self::getContainer()->get(PageRepository::class),
            $projectDir,
            self::getContainer()->getParameter('kernel.environment'),
        );
    }

    public function testIt(): void
    {
        $generator = $this->getStaticAppGenerator();
        $this->overrideStaticDir();

        $generator->generate('localhost.dev');

        $staticDir = $this->getStaticDir();
        self::assertFileExists($staticDir);
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
    }

    public function testGenerateRedirectionHtml(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();

        $siteRegistry = self::getContainer()->get(SiteRegistry::class);
        $locale = $siteRegistry->switchSite('localhost.dev')->get()->getLocale();

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
        $page->setSlug('redirect-dest-test');
        $page->setMainContent('content');
        $page->setRedirectFrom(['old-incoming' => 308]);

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
        $page->setSlug('redirect-dest-test');
        $page->setMainContent('content');
        $page->setRedirectFrom(['old-incoming' => 308]);

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
        // host (prefix suppressed regardless of the flag), and an id-less page 500s in
        // the page-buttons fragment — so persist a real pushword.piedweb.com page.
        $probe = new Page(false);
        $probe->host = 'pushword.piedweb.com';
        $probe->setSlug('worker-reset-probe');
        $probe->locale = 'en';
        $probe->setH1('Worker reset probe');
        $probe->setMainContent('Probe body.');
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

            $destination = $this->getStaticDir().'/worker-reset-regression.html';
            new ReflectionMethod(PageGenerator::class, 'saveAsStatic')
                ->invoke($generator, $generator->generateLivePathFor($page), $destination, $page);

            self::assertFileExists(
                $destination,
                'page must render 200 (errors: '.implode(' | ', $this->getStaticAppGenerator()->getErrors()).')',
            );
            $html = (string) file_get_contents($destination);

            // The bug's fingerprint: a root-relative link that begins with the host
            // segment — href="/pushword.piedweb.com/…". Distinct from the legitimate
            // absolute canonical href="https://pushword.piedweb.com/…".
            self::assertStringNotContainsString(
                'href="/pushword.piedweb.com',
                $html,
                'internal links must not carry the /{host}/ prefix on a static host',
            );
            // Non-vacuity: a page()-built nav link rendered host-less.
            self::assertStringContainsString('href="/installation"', $html);
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
        $commandTester->execute(['host' => 'localhost.dev']);

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
        $commandTester->execute(['host' => 'localhost.dev']);

        self::assertDirectoryDoesNotExist($staleTempDir, 'Stale temp dir should be cleaned up');
        self::assertDirectoryDoesNotExist($staleBackupDir, 'Stale backup dir should be cleaned up');
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
        $commandTester->execute(['host' => 'localhost.dev']);

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
        $commandTester->execute(['host' => 'localhost.dev', '--incremental' => true]);

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

    public function testStateMergeFromFile(): void
    {
        $tempDir = sys_get_temp_dir().'/pushword-state-merge-'.getmypid();
        new Filesystem()->mkdir($tempDir.'/var');

        try {
            $stateManager = new GenerationStateManager($tempDir);
            $stateManager->setLastGenerationTime('test.host');

            // Create a worker state file
            $workerFile = $tempDir.'/var/.worker-0.json';
            file_put_contents($workerFile, json_encode([
                'test.host' => [
                    'pages' => [
                        'page-a' => ['generatedAt' => '2025-01-01T00:00:00+00:00', 'pageUpdatedAt' => '2025-01-01T00:00:00+00:00'],
                        'page-b' => ['generatedAt' => '2025-01-01T00:00:00+00:00', 'pageUpdatedAt' => '2025-01-01T00:00:00+00:00'],
                    ],
                ],
            ]));

            $stateManager->mergeFromFile($workerFile);

            self::assertFalse($stateManager->needsRegeneration('test.host', 'page-a', new DateTimeImmutable('2025-01-01T00:00:00+00:00')));
            self::assertFalse($stateManager->needsRegeneration('test.host', 'page-b', new DateTimeImmutable('2025-01-01T00:00:00+00:00')));
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
            return __DIR__.'/../../skeleton';
        }

        if ('pw.public_media_dir' === $name) {
            return 'media';
        }

        if ('pw.media_dir' === $name) {
            return realpath(__DIR__.'/../../skeleton/media');
        }

        if ('pw.public_dir' === $name) {
            return realpath(__DIR__.'/../../skeleton/public');
        }

        throw new Exception();
    }

    public function getPageRepo(): MockObject
    {
        $page = new Page();
        $page->setH1('Welcome to Pushword !');
        $page->setSlug('homepage');
        $page->locale = 'en';
        $page->createdAt = new DateTime('2 days ago');
        $page->setMainContent('...');

        $pageRepo = $this->createMock(PageRepository::class);
        $pageRepo->method('getPublishedPages')
                  ->willReturn([
                      $page,
                  ]);

        return $pageRepo;
    }
}
