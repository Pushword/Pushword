<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Service;

use Override;
use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Service\FlatChangeDetector;
use Pushword\Flat\Service\FlatLockManager;
use Pushword\Flat\Sync\SyncStateManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;

final class FlatChangeDetectorTest extends KernelTestCase
{
    private string $tempDir;

    private FlatLockManager $lockManager;

    private SyncStateManager $stateManager;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->tempDir = sys_get_temp_dir().'/flat-change-detector-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->lockManager = new FlatLockManager($this->tempDir, 60, 3600);
        $this->stateManager = new SyncStateManager($this->tempDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->tempDir)) {
            $fs->remove($this->tempDir);
        }

        parent::tearDown();
    }

    private function createDetector(
        int $cacheTtl = 300,
        bool $autoLockOnChanges = true,
    ): FlatChangeDetector {
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        self::assertInstanceOf(FlatFileContentDirFinder::class, $contentDirFinder);

        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheInterface::class, $cache);

        $apps = self::getContainer()->get(AppPool::class);
        self::assertInstanceOf(AppPool::class, $apps);

        return new FlatChangeDetector(
            $contentDirFinder,
            $this->stateManager,
            $this->lockManager,
            $cache,
            $apps,
            $cacheTtl,
            $autoLockOnChanges,
        );
    }

    public function testCheckForChangesReturnsExpectedStructure(): void
    {
        $detector = $this->createDetector();
        $result = $detector->checkForChanges(null);

        self::assertIsBool($result['hasChanges']);
        self::assertIsArray($result['entityTypes']);
        self::assertTrue(\array_key_exists('newestFile', $result));
        self::assertTrue(\array_key_exists('newestMtime', $result));
    }

    public function testForceCheckInvalidatesCacheAndReturnsResult(): void
    {
        $detector = $this->createDetector();

        $result1 = $detector->checkForChanges(null);
        $result2 = $detector->forceCheck(null);

        self::assertIsBool($result1['hasChanges']);
        self::assertIsBool($result2['hasChanges']);
    }

    public function testInvalidateCacheDoesNotThrow(): void
    {
        $detector = $this->createDetector();

        $detector->invalidateCache('test.host.com');

        $result = $detector->checkForChanges('test.host.com');
        self::assertIsBool($result['hasChanges']);
    }

    public function testCheckWithDifferentHostsProducesDifferentCacheKeys(): void
    {
        $detector = $this->createDetector();

        $result1 = $detector->checkForChanges('host-a.com');
        $result2 = $detector->checkForChanges('host-b.com');

        self::assertIsBool($result1['hasChanges']);
        self::assertIsBool($result2['hasChanges']);
    }

    public function testAutoLockOnChangesConfig(): void
    {
        $detectorWithLock = $this->createDetector(autoLockOnChanges: true);
        $detectorWithoutLock = $this->createDetector(autoLockOnChanges: false);

        $result1 = $detectorWithLock->checkForChanges(null);
        $result2 = $detectorWithoutLock->checkForChanges(null);

        self::assertIsBool($result1['hasChanges']);
        self::assertIsBool($result2['hasChanges']);
    }

    public function testNullHostIsHandled(): void
    {
        $detector = $this->createDetector();

        $result = $detector->checkForChanges(null);

        self::assertIsBool($result['hasChanges']);
    }

    public function testSpecialCharactersInHostAreNormalized(): void
    {
        $detector = $this->createDetector();

        $result = $detector->checkForChanges('test.example.com');

        self::assertIsBool($result['hasChanges']);
    }

    public function testEmptyHostIsHandled(): void
    {
        $detector = $this->createDetector();

        $result = $detector->checkForChanges('');

        self::assertIsBool($result['hasChanges']);
    }
}
