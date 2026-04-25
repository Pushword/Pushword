<?php

namespace Pushword\StaticGenerator\Tests\Cache;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class CacheClearCommandTest extends KernelTestCase
{
    private string $cacheDir = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Restore pristine DB so page fixtures are available for rendering.
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
        self::bootKernel();

        $siteRegistry = self::getContainer()->get(SiteRegistry::class);
        $staticAppGenerator = self::getContainer()->get(StaticAppGenerator::class);
        $this->cacheDir = $staticAppGenerator->getCacheDir($siteRegistry->switchSite('localhost.dev')->get());
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->cacheDir);
        parent::tearDown();
    }

    private function makeCommandTester(): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line

        return new CommandTester($application->find('pw:cache:clear'));
    }

    public function testCacheClearWarmsFiles(): void
    {
        $commandTester = $this->makeCommandTester();
        $commandTester->execute(['host' => 'localhost.dev']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Clearing cache', $output);
        self::assertStringContainsString('Warming cache for localhost.dev', $output);

        self::assertFileExists($this->cacheDir.'/index.html');
        self::assertFileExists($this->cacheDir.'/index.html.gz');
        self::assertFileExists($this->cacheDir.'/index.html.br');
    }

    public function testCacheClearNoWarmupSkipsRender(): void
    {
        $commandTester = $this->makeCommandTester();

        // Warm first so files exist
        $commandTester->execute(['host' => 'localhost.dev']);
        self::assertFileExists($this->cacheDir.'/index.html');

        // Clear only
        $commandTester->execute(['host' => 'localhost.dev', '--no-warmup' => true]);

        self::assertStringContainsString('Clearing cache', $commandTester->getDisplay());
        self::assertStringNotContainsString('Warming', $commandTester->getDisplay());
        self::assertFileDoesNotExist($this->cacheDir.'/index.html');
    }

    public function testCacheClearSkipsNonStaticSites(): void
    {
        $commandTester = $this->makeCommandTester();

        // pushword.piedweb.com has cache:none
        $commandTester->execute(['host' => 'pushword.piedweb.com']);

        self::assertStringContainsString('No sites', $commandTester->getDisplay());
    }

    public function testCacheClearWithoutHostClearsAllStaticSites(): void
    {
        $commandTester = $this->makeCommandTester();
        $commandTester->execute(['--no-warmup' => true]);

        self::assertStringContainsString('Clearing cache', $commandTester->getDisplay());
    }
}
