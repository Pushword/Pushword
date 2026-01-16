<?php

declare(strict_types=1);

namespace Pushword\Flat\Service;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_decode;
use function Safe\json_encode;

/**
 * Manages editorial locks to prevent concurrent flat/admin modifications.
 * Inspired by LibreOffice's locking mechanism.
 */
final readonly class FlatLockManager
{
    public const string LOCK_TYPE_MANUAL = 'manual';

    public const string LOCK_TYPE_AUTO = 'flat-auto';

    public function __construct(
        private string $varDir,
        private int $defaultTtl = 1800, // 30 minutes
    ) {
    }

    /**
     * Check if a lock is currently active for the given host.
     */
    public function isLocked(?string $host = null): bool
    {
        $lockInfo = $this->getLockInfo($host);

        if (null === $lockInfo) {
            return false;
        }

        // Check if lock has expired
        if ($this->hasExpired($lockInfo)) {
            $this->releaseLock($host);

            return false;
        }

        return true;
    }

    /**
     * Get information about the current lock.
     *
     * @return array{locked: bool, lockedAt: int, lockedBy: string, ttl: int, reason: string}|null
     */
    public function getLockInfo(?string $host = null): ?array
    {
        $filePath = $this->getLockFilePath($host);

        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        /** @var array{locked: bool, lockedAt: int, lockedBy: string, ttl: int, reason: string} */
        return json_decode($content, true);
    }

    /**
     * Acquire a lock for the given host.
     *
     * @return bool True if lock was acquired, false if already locked by another source
     */
    public function acquireLock(?string $host = null, string $reason = 'manual', ?int $ttl = null): bool
    {
        $existingLock = $this->getLockInfo($host);

        // If there's an existing manual lock that hasn't expired, don't allow override
        if (null !== $existingLock
            && ! $this->hasExpired($existingLock)
            && self::LOCK_TYPE_MANUAL === $existingLock['lockedBy']
            && self::LOCK_TYPE_MANUAL !== $reason
        ) {
            return false;
        }

        $lockData = [
            'locked' => true,
            'lockedAt' => time(),
            'lockedBy' => self::LOCK_TYPE_MANUAL === $reason ? self::LOCK_TYPE_MANUAL : self::LOCK_TYPE_AUTO,
            'ttl' => $ttl ?? $this->defaultTtl,
            'reason' => $reason,
        ];

        $this->saveLock($lockData, $host);

        return true;
    }

    /**
     * Release the lock for the given host.
     */
    public function releaseLock(?string $host = null): void
    {
        $filePath = $this->getLockFilePath($host);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Refresh an auto lock (update timestamp).
     * Only works for auto locks, not manual ones.
     */
    public function refreshAutoLock(?string $host = null): void
    {
        $lockInfo = $this->getLockInfo($host);

        if (null === $lockInfo) {
            return;
        }

        // Only refresh auto locks
        if (self::LOCK_TYPE_MANUAL === $lockInfo['lockedBy']) {
            return;
        }

        $lockInfo['lockedAt'] = time();
        $this->saveLock($lockInfo, $host);
    }

    /**
     * Check if the lock type is a manual lock.
     */
    public function isManualLock(?string $host = null): bool
    {
        $lockInfo = $this->getLockInfo($host);

        return null !== $lockInfo && self::LOCK_TYPE_MANUAL === $lockInfo['lockedBy'];
    }

    /**
     * Get remaining lock time in seconds.
     */
    public function getRemainingTime(?string $host = null): int
    {
        $lockInfo = $this->getLockInfo($host);

        if (null === $lockInfo) {
            return 0;
        }

        $expiresAt = $lockInfo['lockedAt'] + $lockInfo['ttl'];
        $remaining = $expiresAt - time();

        return max(0, $remaining);
    }

    /**
     * @param array{locked: bool, lockedAt: int, lockedBy: string, ttl: int, reason: string} $lockInfo
     */
    private function hasExpired(array $lockInfo): bool
    {
        $expiresAt = $lockInfo['lockedAt'] + $lockInfo['ttl'];

        return time() > $expiresAt;
    }

    /**
     * @param array{locked: bool, lockedAt: int, lockedBy: string, ttl: int, reason: string} $lockData
     */
    private function saveLock(array $lockData, ?string $host): void
    {
        $this->ensureDirectory();
        $filePath = $this->getLockFilePath($host);
        file_put_contents($filePath, json_encode($lockData, \JSON_PRETTY_PRINT));
    }

    private function getLockFilePath(?string $host): string
    {
        $hostKey = $this->normalizeHost($host);

        return $this->varDir.'/flat-sync/'.$hostKey.'_lock.json';
    }

    private function normalizeHost(?string $host): string
    {
        if (null === $host || '' === $host) {
            return 'default';
        }

        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $host) ?? $host;
    }

    private function ensureDirectory(): void
    {
        $dir = $this->varDir.'/flat-sync';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
