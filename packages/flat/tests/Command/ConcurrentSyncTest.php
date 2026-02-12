<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Command;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class ConcurrentSyncTest extends KernelTestCase
{
    private BackgroundProcessManager $processManager;

    private FlatLockManager $lockManager;

    private Filesystem $filesystem;

    /** Unique host for lock tests to avoid parallel interference. */
    private string $lockTestHost;

    /** @var string[] */
    private array $cleanupFiles = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->lockTestHost = 'concurrent-test-'.getmypid().'.dev';

        /** @var BackgroundProcessManager $processManager */
        $processManager = self::getContainer()->get(BackgroundProcessManager::class);
        $this->processManager = $processManager;

        /** @var FlatLockManager $lockManager */
        $lockManager = self::getContainer()->get(FlatLockManager::class);
        $this->lockManager = $lockManager;

        $this->filesystem = new Filesystem();
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            @unlink($file);
        }

        // Release any locks
        $this->lockManager->releaseLock($this->lockTestHost);

        parent::tearDown();
    }

    /**
     * Test PID detection logic directly (not via command, to avoid parallel test interference).
     */
    public function testPidFileDetectsRunningProcess(): void
    {
        // Use a unique PID file path to avoid interfering with other tests
        $pidFile = sys_get_temp_dir().'/pushword-test-concurrent-'.getmypid().'.pid';
        $this->cleanupFiles[] = $pidFile;

        $parentPid = posix_getppid();
        $pidData = json_encode(['pid' => $parentPid, 'startTime' => time(), 'commandPattern' => ''], \JSON_THROW_ON_ERROR);
        $this->filesystem->dumpFile($pidFile, $pidData);

        $info = $this->processManager->getProcessInfo($pidFile);
        self::assertTrue($info['isRunning'], 'Process with parent PID should be detected as running');
        self::assertSame($parentPid, $info['pid']);
    }

    public function testStaleProcessDetected(): void
    {
        $pidFile = sys_get_temp_dir().'/pushword-test-stale-'.getmypid().'.pid';
        $this->cleanupFiles[] = $pidFile;

        // Dead PID
        $pidData = json_encode(['pid' => 99999999, 'startTime' => time(), 'commandPattern' => ''], \JSON_THROW_ON_ERROR);
        $this->filesystem->dumpFile($pidFile, $pidData);

        // cleanupStaleProcess should remove the file
        $this->processManager->cleanupStaleProcess($pidFile);
        self::assertFileDoesNotExist($pidFile, 'Stale PID file should be cleaned up');
    }

    public function testWebhookLockBlocksSync(): void
    {
        // Test at service level with unique host to avoid parallel test interference
        self::assertFalse($this->lockManager->isWebhookLocked($this->lockTestHost), 'No lock should be active initially');

        // Acquire webhook lock
        $result = $this->lockManager->acquireWebhookLock($this->lockTestHost, 'Testing webhook lock');
        self::assertTrue($result, 'Should successfully acquire webhook lock');
        self::assertTrue($this->lockManager->isWebhookLocked($this->lockTestHost), 'Lock should be active');

        // Verify lock info
        $lockInfo = $this->lockManager->getLockInfo($this->lockTestHost);
        self::assertNotNull($lockInfo);
        self::assertSame('webhook', $lockInfo['lockedBy']);
        self::assertSame('Testing webhook lock', $lockInfo['reason']);
        self::assertGreaterThan(0, $this->lockManager->getRemainingTime($this->lockTestHost));
    }

    public function testProcessRegistrationAndUnregistration(): void
    {
        // Use a unique PID file path to test register/unregister without racing
        $pidFile = sys_get_temp_dir().'/pushword-test-register-'.getmypid().'.pid';
        $this->cleanupFiles[] = $pidFile;

        $this->processManager->registerProcess($pidFile, 'test-command');
        self::assertFileExists($pidFile, 'PID file should be created after registerProcess');

        $info = $this->processManager->getProcessInfo($pidFile);
        // Current process PID is ignored by isProcessAlive, so isRunning will be false
        self::assertFalse($info['isRunning'], 'Own process should not be considered as "another running process"');

        $this->processManager->unregisterProcess($pidFile);
        self::assertFileDoesNotExist($pidFile, 'PID file should be removed after unregisterProcess');
    }
}
