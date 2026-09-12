<?php

declare(strict_types=1);

namespace Pushword\Flat\Service;

use function Safe\json_decode;
use function Safe\json_encode;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Manages editorial locks to prevent concurrent flat/admin modifications.
 * Inspired by LibreOffice's locking mechanism.
 *
 * Lock types:
 * - manual: CLI command lock (pw:flat:lock)
 * - flat-auto: Automatic lock on flat file changes
 * - webhook: External lock via API for CI/CD workflows
 */
final readonly class FlatLockManager
{
    public const string LOCK_TYPE_MANUAL = 'manual';

    public const string LOCK_TYPE_AUTO = 'flat-auto';

    public const string LOCK_TYPE_WEBHOOK = 'webhook';

    public function __construct(
        private string $varDir,
        private int $defaultTtl = 1800, // 30 minutes
        private int $webhookDefaultTtl = 3600, // 1 hour for webhook locks
        private Filesystem $filesystem = new Filesystem(),
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
     * @return array{locked: bool, lockedAt: int, lockedBy: string, ttl: int, reason: string, lockedByUser?: string}|null
     */
    public function getLockInfo(?string $host = null): ?array
    {
        $filePath = $this->getLockFilePath($host);

        if (! $this->filesystem->exists($filePath)) {
            return null;
        }

        $content = $this->filesystem->readFile($filePath);

        /** @var array{locked: bool, lockedAt: int, lockedBy: string, ttl: int, reason: string, lockedByUser?: string} */
        return json_decode($content, true);
    }

    /**
     * Acquire a lock for the given host.
     *
     * @return bool True if lock was acquired, false if already locked by another source
     */
    public function acquireLock(?string $host = null, string $reason = 'manual', ?int $ttl = null): bool
    {
        $lockType = match ($reason) {
            self::LOCK_TYPE_MANUAL => self::LOCK_TYPE_MANUAL,
            self::LOCK_TYPE_WEBHOOK => self::LOCK_TYPE_WEBHOOK,
            default => self::LOCK_TYPE_AUTO,
        };

        $existingLock = $this->getLockInfo($host);

        // If there's an existing manual or webhook lock that hasn't expired, don't allow override by auto
        if (null !== $existingLock
            && ! $this->hasExpired($existingLock)
            && \in_array($existingLock['lockedBy'], [self::LOCK_TYPE_MANUAL, self::LOCK_TYPE_WEBHOOK], true)
            && self::LOCK_TYPE_AUTO === $lockType
        ) {
            return false;
        }

        $lockData = [
            'locked' => true,
            'lockedAt' => time(),
            'lockedBy' => $lockType,
            'ttl' => $ttl ?? (self::LOCK_TYPE_WEBHOOK === $lockType ? $this->webhookDefaultTtl : $this->defaultTtl),
            'reason' => $reason,
        ];

        $this->saveLock($lockData, $host);

        return true;
    }

    /**
     * Acquire a webhook lock with user information.
     *
     * @return bool True if lock was acquired
     */
    public function acquireWebhookLock(
        ?string $host = null,
        string $reason = 'Webhook lock',
        ?int $ttl = null,
        ?string $userEmail = null,
    ): bool {
        $existingLock = $this->getLockInfo($host);

        // Cannot override an existing non-expired webhook lock
        if (null !== $existingLock
            && ! $this->hasExpired($existingLock)
            && self::LOCK_TYPE_WEBHOOK === $existingLock['lockedBy']
        ) {
            return false;
        }

        $lockData = [
            'locked' => true,
            'lockedAt' => time(),
            'lockedBy' => self::LOCK_TYPE_WEBHOOK,
            'ttl' => $ttl ?? $this->webhookDefaultTtl,
            'reason' => $reason,
            'lockedByUser' => $userEmail,
        ];

        $this->saveLock($lockData, $host);

        return true;
    }

    /**
     * Release the lock for the given host.
     */
    public function releaseLock(?string $host = null): void
    {
        $this->filesystem->remove($this->getLockFilePath($host));
    }

    /**
     * Release the lock only if it's an auto-lock (preserves manual/webhook locks).
     */
    public function releaseAutoLock(?string $host = null): void
    {
        $lockInfo = $this->getLockInfo($host);
        if (null !== $lockInfo && self::LOCK_TYPE_AUTO === $lockInfo['lockedBy']) {
            $this->releaseLock($host);
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
     * Check if the lock type is a webhook lock.
     * Webhook locks block both admin editing and sync operations.
     */
    public function isWebhookLocked(?string $host = null): bool
    {
        if (! $this->isLocked($host)) {
            return false;
        }

        $lockInfo = $this->getLockInfo($host);

        return null !== $lockInfo && self::LOCK_TYPE_WEBHOOK === $lockInfo['lockedBy'];
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
     * @param array{locked: bool, lockedAt: int, lockedBy: string, ttl: int, reason: string, lockedByUser?: string} $lockInfo
     */
    private function hasExpired(array $lockInfo): bool
    {
        $expiresAt = $lockInfo['lockedAt'] + $lockInfo['ttl'];

        return time() > $expiresAt;
    }

    /**
     * @param array{locked: bool, lockedAt: int, lockedBy: string, ttl: int, reason: string, lockedByUser?: string|null} $lockData
     */
    private function saveLock(array $lockData, ?string $host): void
    {
        $this->ensureDirectory();
        $filePath = $this->getLockFilePath($host);
        $this->filesystem->dumpFile($filePath, json_encode($lockData, \JSON_PRETTY_PRINT));
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
        $this->filesystem->mkdir($this->varDir.'/flat-sync');
    }
}
