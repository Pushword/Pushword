<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Component\Filesystem\Filesystem;

final class FlatLockManagerTest extends TestCase
{
    private string $tempDir;

    private FlatLockManager $manager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/flat-lock-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->manager = new FlatLockManager($this->tempDir, 60);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->tempDir)) {
            $fs->remove($this->tempDir);
        }
    }

    public function testAcquireLockCreatesLockFile(): void
    {
        $host = 'test.example.com';

        $result = $this->manager->acquireLock($host, 'manual');

        self::assertTrue($result);
        self::assertTrue($this->manager->isLocked($host));
    }

    public function testIsLockedReturnsTrueWhenLocked(): void
    {
        $host = 'test.example.com';

        self::assertFalse($this->manager->isLocked($host));

        $this->manager->acquireLock($host, 'manual');

        self::assertTrue($this->manager->isLocked($host));
    }

    public function testIsLockedReturnsFalseAfterTtlExpired(): void
    {
        // Create manager with very short TTL
        $manager = new FlatLockManager($this->tempDir, 1);
        $host = 'test.example.com';

        $manager->acquireLock($host, 'manual', 1);
        self::assertTrue($manager->isLocked($host));

        // Wait for TTL to expire
        sleep(2);

        self::assertFalse($manager->isLocked($host));
    }

    public function testReleaseLockDeletesLockFile(): void
    {
        $host = 'test.example.com';

        $this->manager->acquireLock($host, 'manual');
        self::assertTrue($this->manager->isLocked($host));

        $this->manager->releaseLock($host);
        self::assertFalse($this->manager->isLocked($host));
    }

    public function testRefreshAutoLockUpdatesTimestamp(): void
    {
        $host = 'test.example.com';

        $this->manager->acquireLock($host, FlatLockManager::LOCK_TYPE_AUTO);
        $initialLockInfo = $this->manager->getLockInfo($host);

        usleep(100000); // 100ms
        $this->manager->refreshAutoLock($host);

        $refreshedLockInfo = $this->manager->getLockInfo($host);

        self::assertNotNull($initialLockInfo);
        self::assertNotNull($refreshedLockInfo);
        self::assertGreaterThanOrEqual($initialLockInfo['lockedAt'], $refreshedLockInfo['lockedAt']);
    }

    public function testManualLockNotOverriddenByAutoLock(): void
    {
        $host = 'test.example.com';

        // Acquire manual lock
        $this->manager->acquireLock($host, FlatLockManager::LOCK_TYPE_MANUAL);

        // Try to acquire auto lock
        $result = $this->manager->acquireLock($host, 'some reason');

        self::assertFalse($result);
        self::assertTrue($this->manager->isManualLock($host));
    }

    public function testGetLockInfoReturnsCorrectData(): void
    {
        $host = 'test.example.com';
        $reason = 'Test reason';

        $this->manager->acquireLock($host, $reason, 120);

        $lockInfo = $this->manager->getLockInfo($host);

        self::assertNotNull($lockInfo);
        self::assertTrue($lockInfo['locked']);
        self::assertSame(FlatLockManager::LOCK_TYPE_AUTO, $lockInfo['lockedBy']);
        self::assertSame(120, $lockInfo['ttl']);
        self::assertSame($reason, $lockInfo['reason']);
    }

    public function testGetRemainingTime(): void
    {
        $host = 'test.example.com';
        $ttl = 60;

        $this->manager->acquireLock($host, 'manual', $ttl);

        $remaining = $this->manager->getRemainingTime($host);

        self::assertGreaterThan(0, $remaining);
        self::assertLessThanOrEqual($ttl, $remaining);
    }

    public function testIsManualLock(): void
    {
        $host = 'test.example.com';

        $this->manager->acquireLock($host, FlatLockManager::LOCK_TYPE_MANUAL);

        self::assertTrue($this->manager->isManualLock($host));
    }

    public function testAutoLockIsNotManualLock(): void
    {
        $host = 'test.example.com';

        $this->manager->acquireLock($host, 'some reason');

        self::assertFalse($this->manager->isManualLock($host));
    }

    public function testNullHostUsesDefault(): void
    {
        $this->manager->acquireLock(null, 'manual');

        self::assertTrue($this->manager->isLocked(null));
    }
}
