<?php

declare(strict_types=1);

namespace Pushword\Flat\Service;

use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\SyncStateManager;

use function Safe\filemtime;
use function Safe\scandir;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Detects changes in flat files compared to the last sync.
 * Uses caching to avoid expensive file system operations.
 */
final readonly class FlatChangeDetector
{
    public function __construct(
        private FlatFileContentDirFinder $contentDirFinder,
        private SyncStateManager $stateManager,
        private FlatLockManager $lockManager,
        private CacheInterface $cache,
        private AppPool $apps,
        private int $cacheTtl = 300, // 5 minutes default
        private bool $autoLockOnChanges = true,
    ) {
    }

    /**
     * Check if there are changes in flat files since the last sync.
     *
     * @return array{hasChanges: bool, entityTypes: string[], newestFile: string|null, newestMtime: int|null}
     */
    public function checkForChanges(?string $host = null): array
    {
        $hostKey = $this->normalizeHost($host);
        $cacheKey = 'flat_changes_'.$hostKey;

        /** @var array{hasChanges: bool, entityTypes: string[], newestFile: string|null, newestMtime: int|null} */
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($host): array {
            $item->expiresAfter($this->cacheTtl);

            return $this->detectChanges($host);
        });
    }

    /**
     * Force a recheck of changes (invalidate cache).
     *
     * @return array{hasChanges: bool, entityTypes: string[], newestFile: string|null, newestMtime: int|null}
     */
    public function forceCheck(?string $host = null): array
    {
        $this->invalidateCache($host);

        return $this->checkForChanges($host);
    }

    /**
     * Invalidate the cache for a specific host.
     */
    public function invalidateCache(?string $host = null): void
    {
        $hostKey = $this->normalizeHost($host);
        $cacheKey = 'flat_changes_'.$hostKey;
        $this->cache->delete($cacheKey);
    }

    /**
     * Perform the actual change detection.
     *
     * @return array{hasChanges: bool, entityTypes: string[], newestFile: string|null, newestMtime: int|null}
     */
    private function detectChanges(?string $host): array
    {
        $resolvedHost = $host ?? $this->apps->getMainHost();
        if (null === $resolvedHost) {
            return ['hasChanges' => false, 'entityTypes' => [], 'newestFile' => null, 'newestMtime' => null];
        }

        $contentDir = $this->contentDirFinder->get($resolvedHost);
        $lastSyncTime = $this->stateManager->getLastSyncAt($resolvedHost);

        $changedEntityTypes = [];
        /** @var array{file: string|null, mtime: int|null} $newest */
        $newest = ['file' => null, 'mtime' => null];

        // Check for page changes (.md files)
        $pageChanges = $this->checkDirectoryForChanges($contentDir, '*.md', $lastSyncTime);
        if ($pageChanges['hasChanges']) {
            $changedEntityTypes[] = 'page';
            $newest = $this->updateNewest($newest, $pageChanges['newestFile'], $pageChanges['newestMtime']);
        }

        // Check for media changes (in media subdirectory)
        $mediaDir = $contentDir.'/media';
        if (is_dir($mediaDir)) {
            $mediaChanges = $this->checkDirectoryForChanges($mediaDir, '*', $lastSyncTime, ['index.csv', '*.conflicts.csv']);
            if ($mediaChanges['hasChanges']) {
                $changedEntityTypes[] = 'media';
                $newest = $this->updateNewest($newest, $mediaChanges['newestFile'], $mediaChanges['newestMtime']);
            }
        }

        // Check for conversation changes
        $conversationFile = $contentDir.'/conversation.csv';
        if (file_exists($conversationFile)) {
            $mtime = filemtime($conversationFile);
            if ($mtime > $lastSyncTime) {
                $changedEntityTypes[] = 'conversation';
                $newest = $this->updateNewest($newest, $conversationFile, $mtime);
            }
        }

        $hasChanges = [] !== $changedEntityTypes;

        // Auto-lock if changes detected
        if ($hasChanges && $this->autoLockOnChanges) {
            $this->lockManager->acquireLock($host, 'Fichiers flat modifiés');
        }

        return [
            'hasChanges' => $hasChanges,
            'entityTypes' => $changedEntityTypes,
            'newestFile' => $newest['file'],
            'newestMtime' => $newest['mtime'],
        ];
    }

    /**
     * Update the newest file/mtime tracking.
     *
     * @param array{file: string|null, mtime: int|null} $current
     *
     * @return array{file: string|null, mtime: int|null}
     */
    private function updateNewest(array $current, ?string $file, ?int $mtime): array
    {
        if (null === $mtime) {
            return $current;
        }

        if (null === $current['mtime'] || $mtime > $current['mtime']) {
            return ['file' => $file, 'mtime' => $mtime];
        }

        return $current;
    }

    /**
     * Check a directory for files modified after the given timestamp.
     *
     * @param string[] $exclude Patterns to exclude
     *
     * @return array{hasChanges: bool, newestFile: string|null, newestMtime: int|null}
     */
    private function checkDirectoryForChanges(
        string $dir,
        string $pattern,
        int $lastSyncTime,
        array $exclude = [],
    ): array {
        if (! is_dir($dir)) {
            return ['hasChanges' => false, 'newestFile' => null, 'newestMtime' => null];
        }

        $newestFile = null;
        $newestMtime = null;
        $hasChanges = false;

        $this->scanDirectory($dir, $pattern, $exclude, static function (string $filePath) use ($lastSyncTime, &$hasChanges, &$newestFile, &$newestMtime): void {
            $mtime = filemtime($filePath);
            if ($mtime > $lastSyncTime) {
                $hasChanges = true;
                if (null === $newestMtime || $mtime > $newestMtime) {
                    $newestFile = $filePath;
                    $newestMtime = $mtime;
                }
            }
        });

        return ['hasChanges' => $hasChanges, 'newestFile' => $newestFile, 'newestMtime' => $newestMtime];
    }

    /**
     * Recursively scan a directory and call the callback for matching files.
     *
     * @param string[] $exclude
     */
    private function scanDirectory(string $dir, string $pattern, array $exclude, callable $callback): void
    {
        /** @var string[] $entries */
        $entries = scandir($dir);

        foreach ($entries as $entry) {
            if (\in_array($entry, ['.', '..'], true)) {
                continue;
            }

            // Skip temporary/lock files
            if ($this->isTempOrLockFile($entry)) {
                continue;
            }

            // Skip backup files
            if (str_ends_with($entry, '~')) {
                continue;
            }

            if (str_contains($entry, '~conflict-')) {
                continue;
            }

            // Skip excluded patterns
            if ($this->matchesExcludePattern($entry, $exclude)) {
                continue;
            }

            $path = $dir.'/'.$entry;

            if (is_dir($path)) {
                $this->scanDirectory($path, $pattern, $exclude, $callback);

                continue;
            }

            // Check if file matches pattern
            if ('*' === $pattern || fnmatch($pattern, $entry)) {
                $callback($path);
            }
        }
    }

    /**
     * @param string[] $patterns
     */
    private function matchesExcludePattern(string $filename, array $patterns): bool
    {
        return array_any($patterns, static fn ($pattern): bool => fnmatch($pattern, $filename));
    }

    private function isTempOrLockFile(string $filename): bool
    {
        // LibreOffice lock files
        if (str_starts_with($filename, '.~lock.') && str_ends_with($filename, '#')) {
            return true;
        }

        // MS Office temp files
        if (str_starts_with($filename, '~$')) {
            return true;
        }

        // Generic temp files
        return str_starts_with($filename, '.~') || str_starts_with($filename, '~');
    }

    private function normalizeHost(?string $host): string
    {
        if (null === $host || '' === $host) {
            return 'default';
        }

        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $host) ?? $host;
    }
}
