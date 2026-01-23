<?php

declare(strict_types=1);

namespace Pushword\Flat\Sync;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_decode;
use function Safe\json_encode;

/**
 * Manages sync state tracking for flat file synchronization.
 * Stores timestamps and conflict logs per host.
 */
final readonly class SyncStateManager
{
    private const int MAX_CONFLICT_ENTRIES = 100;

    public function __construct(
        private string $varDir,
    ) {
    }

    /**
     * Get the last sync time for a specific entity type and host.
     */
    public function getLastSyncTime(string $entityType, ?string $host = null): int
    {
        $state = $this->loadState($host);
        $value = $state[$entityType] ?? 0;

        return \is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Get the last sync direction (import/export).
     */
    public function getLastDirection(?string $host = null): ?string
    {
        $state = $this->loadState($host);
        $direction = $state['lastDirection'] ?? null;

        return \is_string($direction) ? $direction : null;
    }

    /**
     * Get the timestamp of the last sync operation.
     */
    public function getLastSyncAt(?string $host = null): int
    {
        $state = $this->loadState($host);
        $value = $state['lastSyncAt'] ?? 0;

        return \is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Record an import operation for a specific entity type.
     */
    public function recordImport(string $entityType, ?string $host = null): void
    {
        $this->recordSync($entityType, 'import', $host);
    }

    /**
     * Record an export operation for a specific entity type.
     */
    public function recordExport(string $entityType, ?string $host = null): void
    {
        $this->recordSync($entityType, 'export', $host);
    }

    /**
     * Record a conflict that occurred during sync.
     *
     * @param array{
     *     entityType: string,
     *     entityId: int|string|null,
     *     field?: string,
     *     flatValue?: string,
     *     dbValue?: string,
     *     winner: string,
     *     backupFile?: string
     * } $conflictData
     */
    public function recordConflict(array $conflictData, ?string $host = null): void
    {
        $conflicts = $this->loadConflicts($host);

        $conflicts[] = [
            'conflictId' => uniqid('conflict_', true),
            'conflictDate' => date('Y-m-d H:i:s'),
            ...$conflictData,
        ];

        // Keep only the last MAX_CONFLICT_ENTRIES entries
        if (\count($conflicts) > self::MAX_CONFLICT_ENTRIES) {
            $conflicts = \array_slice($conflicts, -self::MAX_CONFLICT_ENTRIES);
        }

        $this->saveConflicts($conflicts, $host);
    }

    /**
     * Get all recorded conflicts for a host.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getConflicts(?string $host = null): array
    {
        return $this->loadConflicts($host);
    }

    /**
     * Clear all recorded conflicts for a host.
     */
    public function clearConflicts(?string $host = null): void
    {
        $filePath = $this->getConflictsFilePath($host);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Reset all sync state for a host.
     */
    public function resetState(?string $host = null): void
    {
        $filePath = $this->getStateFilePath($host);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    private function recordSync(string $entityType, string $direction, ?string $host): void
    {
        $state = $this->loadState($host);
        $now = time();

        $state[$entityType] = $now;
        $state['lastDirection'] = $direction;
        $state['lastSyncAt'] = $now;

        $this->saveState($state, $host);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadState(?string $host): array
    {
        $filePath = $this->getStateFilePath($host);

        if (! file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);

        /** @var array<string, mixed> */
        return json_decode($content, true);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveState(array $state, ?string $host): void
    {
        $this->ensureDirectory();
        $filePath = $this->getStateFilePath($host);
        file_put_contents($filePath, json_encode($state, \JSON_PRETTY_PRINT));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadConflicts(?string $host): array
    {
        $filePath = $this->getConflictsFilePath($host);

        if (! file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);

        /** @var array<int, array<string, mixed>> */
        return json_decode($content, true);
    }

    /**
     * @param array<int, array<string, mixed>> $conflicts
     */
    private function saveConflicts(array $conflicts, ?string $host): void
    {
        $this->ensureDirectory();
        $filePath = $this->getConflictsFilePath($host);
        file_put_contents($filePath, json_encode($conflicts, \JSON_PRETTY_PRINT));
    }

    private function getStateFilePath(?string $host): string
    {
        $hostKey = $this->normalizeHost($host);

        return $this->varDir.'/flat-sync/'.$hostKey.'.json';
    }

    private function getConflictsFilePath(?string $host): string
    {
        $hostKey = $this->normalizeHost($host);

        return $this->varDir.'/flat-sync/'.$hostKey.'_conflicts.json';
    }

    private function normalizeHost(?string $host): string
    {
        if (null === $host || '' === $host) {
            return 'default';
        }

        // Replace special characters with underscores
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
