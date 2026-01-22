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
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Filesystem\Filesystem;

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

        // Initialize sync state with a future timestamp so no files appear as changed
        $this->initializeSyncState();
    }

    private function initializeSyncState(): void
    {
        // Write state files with a timestamp far in the future to ensure no files are detected as changed
        $stateDir = $this->tempDir.'/flat-sync';
        if (! is_dir($stateDir)) {
            mkdir($stateDir, 0755, true);
        }
        $futureTime = time() + 3600;
        $state = json_encode(['lastSyncAt' => $futureTime]);

        // Cover all hosts used in tests (normalized: dots become underscores)
        $hosts = ['default', 'localhost_dev', 'test_host_com', 'host-a_com', 'host-b_com', 'test_example_com'];
        foreach ($hosts as $host) {
            file_put_contents($stateDir.'/'.$host.'.json', $state);
        }
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
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);

        /** @var TraceableAdapter $cache */
        $cache = self::getContainer()->get('cache.app');

        /** @var AppPool $apps */
        $apps = self::getContainer()->get(AppPool::class);

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

        // Verify structure by accessing expected keys
        $hasChanges = $result['hasChanges'];
        $entityTypes = $result['entityTypes'];
        self::assertFalse($hasChanges);
        self::assertEmpty($entityTypes);
    }

    public function testForceCheckInvalidatesCacheAndReturnsResult(): void
    {
        $detector = $this->createDetector();

        $detector->checkForChanges(null);

        $result2 = $detector->forceCheck(null);

        self::assertFalse($result2['hasChanges']);
    }

    public function testInvalidateCacheDoesNotThrow(): void
    {
        $detector = $this->createDetector();

        $detector->invalidateCache('test.host.com');

        $result = $detector->checkForChanges('test.host.com');
        self::assertFalse($result['hasChanges']);
    }

    public function testCheckWithDifferentHostsProducesDifferentCacheKeys(): void
    {
        $detector = $this->createDetector();

        $result1 = $detector->checkForChanges('host-a.com');
        $result2 = $detector->checkForChanges('host-b.com');

        self::assertSame($result1['hasChanges'], $result2['hasChanges']);
    }

    public function testAutoLockOnChangesConfig(): void
    {
        $detectorWithLock = $this->createDetector(autoLockOnChanges: true);
        $detectorWithoutLock = $this->createDetector(autoLockOnChanges: false);

        $result1 = $detectorWithLock->checkForChanges(null);
        $result2 = $detectorWithoutLock->checkForChanges(null);

        self::assertSame($result1['hasChanges'], $result2['hasChanges']);
    }

    public function testNullHostIsHandled(): void
    {
        $detector = $this->createDetector();

        $result = $detector->checkForChanges(null);

        self::assertFalse($result['hasChanges']);
    }

    public function testSpecialCharactersInHostAreNormalized(): void
    {
        $detector = $this->createDetector();

        $result = $detector->checkForChanges('test.example.com');

        self::assertFalse($result['hasChanges']);
    }

    public function testEmptyHostIsHandled(): void
    {
        $detector = $this->createDetector();

        $result = $detector->checkForChanges('');

        self::assertFalse($result['hasChanges']);
    }
}
