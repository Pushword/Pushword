<?php

declare(strict_types=1);

namespace Pushword\Admin\Service;

use Pushword\Core\Entity\User;

/**
 * Manages page edit locks to prevent concurrent editing conflicts.
 * Uses JSON files stored in var/page-locks/ directory.
 * Locks expire automatically after TTL seconds without ping.
 * Tracks both userId and tabId to detect same user in multiple tabs.
 */
final readonly class PageEditLockManager
{
    private const int DEFAULT_TTL = 12;

    public function __construct(
        private string $varDir,
    ) {
    }

    /**
     * Check if a page is currently locked by another user or tab.
     */
    public function isLockedByOther(int $pageId, ?User $currentUser, ?string $currentTabId = null): bool
    {
        $lockInfo = $this->getLockInfo($pageId);

        if (null === $lockInfo || $this->hasExpired($lockInfo)) {
            return false;
        }

        if (null === $currentUser) {
            return true;
        }

        // Different user = locked by other
        if ($lockInfo['userId'] !== $currentUser->getId()) {
            return true;
        }

        // Same user but different tab = locked by other (if tabId provided)
        return null !== $currentTabId && isset($lockInfo['tabId']) && $lockInfo['tabId'] !== $currentTabId;
    }

    /**
     * @return array{pageId: int, userId: int, userEmail: string, username: string, tabId: string|null, lockedAt: int, lastPingAt: int, lastSavedAt: int|null}|null
     */
    public function getLockInfo(int $pageId): ?array
    {
        $filePath = $this->getLockFilePath($pageId);

        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if (false === $content) {
            return null;
        }

        $data = json_decode($content, true);
        if (! \is_array($data)) {
            return null;
        }

        // Ensure tabId key exists for backwards compatibility
        if (! isset($data['tabId'])) {
            $data['tabId'] = null;
        }

        /** @var array{pageId: int, userId: int, userEmail: string, username: string, tabId: string|null, lockedAt: int, lastPingAt: int, lastSavedAt: int|null} $data */
        return $data;
    }

    /**
     * Acquire or refresh a lock for the given page.
     * Returns true if lock was acquired/refreshed, false if locked by another user/tab.
     */
    public function acquireOrRefresh(int $pageId, User $user, ?string $tabId = null): bool
    {
        $existingLock = $this->getLockInfo($pageId);
        $userId = $user->getId();

        if (null === $userId) {
            return false;
        }

        if (null !== $existingLock && ! $this->hasExpired($existingLock)) {
            // Different user = cannot acquire
            if ($existingLock['userId'] !== $userId) {
                return false;
            }

            // Same user but different tab = cannot acquire (if tabId provided)
            if (null !== $tabId && isset($existingLock['tabId']) && $existingLock['tabId'] !== $tabId) {
                return false;
            }
        }

        $lockData = [
            'pageId' => $pageId,
            'userId' => $userId,
            'userEmail' => $user->email,
            'username' => $user->getUsername(),
            'tabId' => $tabId,
            'lockedAt' => $existingLock['lockedAt'] ?? time(),
            'lastPingAt' => time(),
            'lastSavedAt' => $existingLock['lastSavedAt'] ?? null,
        ];

        $this->saveLock($lockData, $pageId);

        return true;
    }

    /**
     * Update the lastSavedAt timestamp when the page is saved.
     */
    public function markSaved(int $pageId, User $user): void
    {
        $lockInfo = $this->getLockInfo($pageId);

        if (null === $lockInfo || $lockInfo['userId'] !== $user->getId()) {
            return;
        }

        $lockInfo['lastSavedAt'] = time();
        $lockInfo['lastPingAt'] = time();
        $this->saveLock($lockInfo, $pageId);
    }

    /**
     * @param array{pageId: int, userId: int, userEmail: string, username: string, tabId: string|null, lockedAt: int, lastPingAt: int, lastSavedAt: int|null} $lockInfo
     */
    private function hasExpired(array $lockInfo): bool
    {
        $expiresAt = $lockInfo['lastPingAt'] + self::DEFAULT_TTL;

        return time() > $expiresAt;
    }

    /**
     * @param array{pageId: int, userId: int, userEmail: string, username: string, tabId: string|null, lockedAt: int, lastPingAt: int, lastSavedAt: int|null} $lockData
     */
    private function saveLock(array $lockData, int $pageId): void
    {
        $this->ensureDirectory();
        $filePath = $this->getLockFilePath($pageId);
        file_put_contents($filePath, json_encode($lockData, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
    }

    private function getLockFilePath(int $pageId): string
    {
        return $this->varDir.'/page-locks/page_'.$pageId.'.json';
    }

    private function ensureDirectory(): void
    {
        $dir = $this->varDir.'/page-locks';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
