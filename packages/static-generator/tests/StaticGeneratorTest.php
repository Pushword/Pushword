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
use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\HtaccessGenerator;
use Pushword\StaticGenerator\Generator\MediaGenerator;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function Safe\realpath;

use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

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

        self::assertFileExists($this->getStaticDir().'/.htaccess');
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
