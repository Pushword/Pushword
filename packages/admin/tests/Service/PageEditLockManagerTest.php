<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Admin\Service\PageEditLockManager;
use Pushword\Core\Entity\User;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;

final class PageEditLockManagerTest extends TestCase
{
    private string $tempDir;

    private PageEditLockManager $manager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/page-lock-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->manager = new PageEditLockManager($this->tempDir);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->tempDir)) {
            $fs->remove($this->tempDir);
        }
    }

    private function createUser(int $id, string $email): User
    {
        $user = new User();
        $reflection = new ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($user, $id);

        $user->email = $email;

        return $user;
    }

    public function testAcquireOrRefreshCreatesLockFile(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');

        $result = $this->manager->acquireOrRefresh($pageId, $user);

        self::assertTrue($result);
        self::assertFalse($this->manager->isLockedByOther($pageId, $user));
    }

    public function testIsLockedByOtherReturnsFalseForOwner(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');

        $this->manager->acquireOrRefresh($pageId, $user);

        self::assertFalse($this->manager->isLockedByOther($pageId, $user));
    }

    public function testIsLockedByOtherReturnsTrueForDifferentUser(): void
    {
        $pageId = 1;
        $user1 = $this->createUser(1, 'user1@test.com');
        $user2 = $this->createUser(2, 'user2@test.com');

        $this->manager->acquireOrRefresh($pageId, $user1);

        self::assertTrue($this->manager->isLockedByOther($pageId, $user2));
    }

    public function testLockExpiresWithTtl(): void
    {
        // This test uses a real sleep, so we use a mock approach
        $pageId = 1;
        $user1 = $this->createUser(1, 'user1@test.com');
        $user2 = $this->createUser(2, 'user2@test.com');

        $this->manager->acquireOrRefresh($pageId, $user1);

        // Manipulate the lock file to simulate expiration
        $lockFile = $this->tempDir.'/page-locks/page_'.$pageId.'.json';
        $lockData = json_decode((string) file_get_contents($lockFile), true);
        self::assertIsArray($lockData);
        $lockData['lastPingAt'] = time() - 20; // 20 seconds ago (expired)
        file_put_contents($lockFile, json_encode($lockData, \JSON_PRETTY_PRINT));

        // Now user2 should be able to acquire the lock
        self::assertFalse($this->manager->isLockedByOther($pageId, $user2));
    }

    public function testAcquireFailsWhenLockedByAnother(): void
    {
        $pageId = 1;
        $user1 = $this->createUser(1, 'user1@test.com');
        $user2 = $this->createUser(2, 'user2@test.com');

        $this->manager->acquireOrRefresh($pageId, $user1);
        $result = $this->manager->acquireOrRefresh($pageId, $user2);

        self::assertFalse($result);
    }

    public function testRefreshUpdatesLastPingAt(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');

        $this->manager->acquireOrRefresh($pageId, $user);
        $initialLockInfo = $this->manager->getLockInfo($pageId);

        usleep(100000); // 100ms
        $this->manager->acquireOrRefresh($pageId, $user);

        $refreshedLockInfo = $this->manager->getLockInfo($pageId);

        self::assertNotNull($initialLockInfo);
        self::assertNotNull($refreshedLockInfo);
        self::assertGreaterThanOrEqual($initialLockInfo['lastPingAt'], $refreshedLockInfo['lastPingAt']);
    }

    public function testMarkSavedUpdatesLastSavedAt(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');

        $this->manager->acquireOrRefresh($pageId, $user);
        $initialLockInfo = $this->manager->getLockInfo($pageId);
        self::assertNotNull($initialLockInfo);
        self::assertNull($initialLockInfo['lastSavedAt']);

        $this->manager->markSaved($pageId, $user);

        $updatedLockInfo = $this->manager->getLockInfo($pageId);
        self::assertNotNull($updatedLockInfo);
        self::assertNotNull($updatedLockInfo['lastSavedAt']);
    }

    public function testMarkSavedOnlyWorksForOwner(): void
    {
        $pageId = 1;
        $user1 = $this->createUser(1, 'user1@test.com');
        $user2 = $this->createUser(2, 'user2@test.com');

        $this->manager->acquireOrRefresh($pageId, $user1);

        // User2 trying to mark as saved should have no effect
        $this->manager->markSaved($pageId, $user2);

        $lockInfo = $this->manager->getLockInfo($pageId);
        self::assertNotNull($lockInfo);
        self::assertNull($lockInfo['lastSavedAt']);
    }

    public function testGetLockInfoReturnsCorrectData(): void
    {
        $pageId = 42;
        $user = $this->createUser(5, 'test@example.com');

        $this->manager->acquireOrRefresh($pageId, $user);

        $lockInfo = $this->manager->getLockInfo($pageId);

        self::assertNotNull($lockInfo);
        self::assertSame(42, $lockInfo['pageId']);
        self::assertSame(5, $lockInfo['userId']);
        self::assertSame('test@example.com', $lockInfo['userEmail']);
        self::assertSame('test@example.com', $lockInfo['username']);
        self::assertNull($lockInfo['lastSavedAt']);
    }

    public function testGetLockInfoReturnsNullWhenNoLock(): void
    {
        $lockInfo = $this->manager->getLockInfo(999);

        self::assertNull($lockInfo);
    }

    public function testIsLockedByOtherReturnsFalseWhenNoLock(): void
    {
        $user = $this->createUser(1, 'user@test.com');

        self::assertFalse($this->manager->isLockedByOther(999, $user));
    }

    public function testAcquireWithNullUserIdReturnsFalse(): void
    {
        $pageId = 1;
        $user = new User();
        $user->email = 'test@test.com';
        // id is null

        $result = $this->manager->acquireOrRefresh($pageId, $user);

        self::assertFalse($result);
    }

    public function testSameUserDifferentTabIsLockedByOther(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');
        $tabId1 = 'tab-1';
        $tabId2 = 'tab-2';

        // User acquires lock with tab 1
        $this->manager->acquireOrRefresh($pageId, $user, $tabId1);

        // Same user with different tab should see it as locked
        self::assertTrue($this->manager->isLockedByOther($pageId, $user, $tabId2));
    }

    public function testSameUserSameTabIsNotLockedByOther(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');
        $tabId = 'tab-1';

        // User acquires lock with tab 1
        $this->manager->acquireOrRefresh($pageId, $user, $tabId);

        // Same user with same tab should NOT see it as locked
        self::assertFalse($this->manager->isLockedByOther($pageId, $user, $tabId));
    }

    public function testSameUserDifferentTabCannotAcquire(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');
        $tabId1 = 'tab-1';
        $tabId2 = 'tab-2';

        // User acquires lock with tab 1
        $result1 = $this->manager->acquireOrRefresh($pageId, $user, $tabId1);
        self::assertTrue($result1);

        // Same user with different tab cannot acquire
        $result2 = $this->manager->acquireOrRefresh($pageId, $user, $tabId2);
        self::assertFalse($result2);
    }

    public function testSameUserSameTabCanRefresh(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');
        $tabId = 'tab-1';

        // User acquires lock with tab 1
        $result1 = $this->manager->acquireOrRefresh($pageId, $user, $tabId);
        self::assertTrue($result1);

        // Same user with same tab can refresh
        $result2 = $this->manager->acquireOrRefresh($pageId, $user, $tabId);
        self::assertTrue($result2);
    }

    public function testTabIdStoredInLockInfo(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');
        $tabId = 'my-unique-tab-id';

        $this->manager->acquireOrRefresh($pageId, $user, $tabId);

        $lockInfo = $this->manager->getLockInfo($pageId);

        self::assertNotNull($lockInfo);
        self::assertSame($tabId, $lockInfo['tabId']);
    }

    public function testBackwardsCompatibilityWithoutTabId(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');

        // Acquire without tabId (backwards compatible)
        $result = $this->manager->acquireOrRefresh($pageId, $user);

        self::assertTrue($result);

        $lockInfo = $this->manager->getLockInfo($pageId);
        self::assertNotNull($lockInfo);
        self::assertNull($lockInfo['tabId']);

        // Same user without tabId can still refresh
        $result2 = $this->manager->acquireOrRefresh($pageId, $user);
        self::assertTrue($result2);
    }

    public function testExpiredLockAllowsDifferentTabToAcquire(): void
    {
        $pageId = 1;
        $user = $this->createUser(1, 'user@test.com');
        $tabId1 = 'tab-1';
        $tabId2 = 'tab-2';

        // User acquires lock with tab 1
        $this->manager->acquireOrRefresh($pageId, $user, $tabId1);

        // Manipulate the lock file to simulate expiration
        $lockFile = $this->tempDir.'/page-locks/page_'.$pageId.'.json';
        $lockData = json_decode((string) file_get_contents($lockFile), true);
        self::assertIsArray($lockData);
        $lockData['lastPingAt'] = time() - 20; // 20 seconds ago (expired)
        file_put_contents($lockFile, json_encode($lockData, \JSON_PRETTY_PRINT));

        // Same user with different tab can now acquire (lock expired)
        $result = $this->manager->acquireOrRefresh($pageId, $user, $tabId2);
        self::assertTrue($result);
    }
}
