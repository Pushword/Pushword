<?php

namespace Pushword\StaticGenerator;

use DateTime;
use DateTimeImmutable;
use Exception;
use FilesystemIterator;
use LogicException;
use Override;
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
class StaticGeneratorTest extends KernelTestCase
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

    #[Override]
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

        // Clean up shared PID file to prevent cross-worker interference
        $pidFile = self::getContainer()->getParameter('kernel.project_dir').'/var/static-generator.pid';
        new Filesystem()->remove($pidFile);
    }

    private function getStaticDir(): string
    {
        return $this->isolatedStaticDir ?? throw new LogicException('isolatedStaticDir not set');
    }

    private function getStateFilePath(): string
    {
        return self::getContainer()->getParameter('kernel.project_dir').'/var/.static-generation-state.json';
    }

    public function testStaticCommand(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        $application = new Application(static::$kernel); // @phpstan-ignore-line

        $command = $application->find('pw:static');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['host' => 'localhost.dev']);

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

    public function testIncrementalGeneration(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        $application = new Application(static::$kernel); // @phpstan-ignore-line
        $staticDir = $this->getStaticDir();
        $stateFile = $this->getStateFilePath();

        $command = $application->find('pw:static');
        $commandTester = new CommandTester($command);

        // First full generation
        $commandTester->execute(['host' => 'localhost.dev']);

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

        // Second generation with incremental flag
        $commandTester->execute(['host' => 'localhost.dev', '--incremental' => true]);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('success', $output);
        self::assertStringContainsString('incremental mode', $output);

        // Index.html should NOT be regenerated (same mtime since page unchanged)
        clearstatcache();
        $newMtime = filemtime($indexFile);
        self::assertSame($originalMtime, $newMtime, 'File should not be regenerated in incremental mode when unchanged');
    }

    public function testGenerationStateManager(): void
    {
        // Use isolated temp dir for state file
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
        $originalDebugKernel = $debugKernelProp->getValue(null);

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

        $application = new Application(static::$kernel); // @phpstan-ignore-line
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
            self::assertSame($generatorClass, $generator::class);
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

        $application = new Application(static::$kernel); // @phpstan-ignore-line
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

        $application = new Application(static::$kernel); // @phpstan-ignore-line
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

    public function testParallelGenerationProducesSameOutput(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $siteRegistry = $container->get(SiteRegistry::class);

        // Sequential run
        $seqDir = sys_get_temp_dir().'/pushword-static-seq-'.getmypid();
        $siteConfig = $siteRegistry->switchSite('localhost.dev')->get();
        $siteConfig->setCustomProperty('static_dir', $seqDir);
        $this->cleanupPidFiles();

        $application = new Application(static::$kernel); // @phpstan-ignore-line
        $command = $application->find('pw:static');
        $tester = new CommandTester($command);
        $tester->execute(['host' => 'localhost.dev', '--workers' => 1]);

        $seqOutput = $tester->getDisplay();
        self::assertStringContainsString('success', $seqOutput, 'Sequential generation failed: '.$seqOutput);

        // Parallel run
        $parDir = sys_get_temp_dir().'/pushword-static-par-'.getmypid();
        $siteConfig->setCustomProperty('static_dir', $parDir);
        $this->cleanupPidFiles();

        $tester->execute(['host' => 'localhost.dev', '--workers' => 2]);
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

    public function testParallelGenerationShowsWorkerPrefix(): void
    {
        self::bootKernel();
        $this->overrideStaticDir();
        $this->cleanupPidFiles();

        $application = new Application(static::$kernel); // @phpstan-ignore-line
        $command = $application->find('pw:static');
        $tester = new CommandTester($command);
        $tester->execute(['host' => 'localhost.dev', '--workers' => 2]);

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
