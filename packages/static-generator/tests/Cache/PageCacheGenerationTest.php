<?php

namespace Pushword\StaticGenerator\Tests\Cache;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * StaticAppGenerator::generatePage() is the per-page refresh entry point of
 * `cache: static` (PageCacheRefreshHandler dispatches to it). Its handler test
 * mocks PageCacheGeneratorInterface away, so the redirection of the output into
 * the cache dir is only exercised here.
 */
#[Group('integration')]
final class PageCacheGenerationTest extends KernelTestCase
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

        $this->cacheDir = $this->getGenerator()->getCacheDir($this->getSiteConfig());
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->cacheDir);
        parent::tearDown();
    }

    private function getGenerator(): StaticAppGenerator
    {
        return self::getContainer()->get(StaticAppGenerator::class);
    }

    private function getSiteConfig(): SiteConfig
    {
        return self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev')->get();
    }

    /**
     * A single-page refresh must land in the cache dir, not in the site's
     * static_dir: writing it there would leave the served cache stale forever.
     *
     * Only the cache dir is asserted on — it is isolated per ParaTest worker via
     * PUSHWORD_TEST_VAR_DIR, whereas static_dir resolves to the shared
     * dev-app/static/ tree that concurrent workers also write to.
     */
    public function testGeneratePageWritesIntoTheCacheDir(): void
    {
        self::assertNotSame(
            $this->cacheDir,
            $this->getSiteConfig()->getStr('static_dir'),
            'the fixture must keep the two dirs distinct for this test to mean anything',
        );

        $this->getGenerator()->generatePage('localhost.dev', 'homepage');

        self::assertFileExists($this->cacheDir.'/index.html');
        self::assertStringContainsString('<html', (string) file_get_contents($this->cacheDir.'/index.html'));
    }
}
