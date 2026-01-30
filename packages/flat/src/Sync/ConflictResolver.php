<?php

declare(strict_types=1);

namespace Pushword\Flat\Sync;

use DateTimeInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Service\AdminNotificationService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Resolves conflicts between flat files and database during sync.
 * Strategy: Most recent wins, backup created for the losing version.
 */
final class ConflictResolver
{
    private ?OutputInterface $output = null;

    private ?string $currentHost = null;

    public function __construct(
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly SyncStateManager $stateManager,
        private readonly ?AdminNotificationService $notificationService = null,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function setCurrentHost(?string $host): void
    {
        $this->currentHost = $host;
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * Resolve a conflict for a Page entity.
     *
     * @return array{hasConflict: bool, winner: string|null, backupFile: string|null}
     */
    public function resolvePageConflict(
        Page $page,
        string $filePath,
        DateTimeInterface $fileModifiedAt,
        DateTimeInterface $lastSyncAt,
    ): array {
        // No conflict if file or DB was not modified since last sync
        if ($fileModifiedAt <= $lastSyncAt && $page->updatedAt <= $lastSyncAt) {
            return ['hasConflict' => false, 'winner' => null, 'backupFile' => null];
        }

        // Both modified since last sync = conflict
        if ($fileModifiedAt > $lastSyncAt && $page->updatedAt > $lastSyncAt) {
            // Most recent wins
            $winner = $fileModifiedAt >= $page->updatedAt ? 'flat' : 'db';
            $backupFile = null;

            if ('flat' === $winner) {
                // DB version loses, create backup of file (which will be overwritten by import)
                $backupFile = $this->createMarkdownBackup($filePath, 'db');
                $this->logConflict('Page', (string) $page->id, 'flat', $backupFile);
            } else {
                // Flat version loses, create backup before it's overwritten
                $backupFile = $this->createMarkdownBackup($filePath, 'flat');
                $this->logConflict('Page', (string) $page->id, 'db', $backupFile);
            }

            $conflictData = [
                'entityType' => 'page',
                'entityId' => $page->id,
                'winner' => $winner,
            ];
            if (null !== $backupFile) {
                $conflictData['backupFile'] = $backupFile;
            }

            $this->stateManager->recordConflict($conflictData, $page->host);

            return ['hasConflict' => true, 'winner' => $winner, 'backupFile' => $backupFile];
        }

        // Only one side modified - no conflict
        return ['hasConflict' => false, 'winner' => null, 'backupFile' => null];
    }

    /**
     * Resolve a conflict for a Media entity.
     *
     * @return array{hasConflict: bool, winner: string|null, conflictData: array<string, mixed>|null}
     */
    public function resolveMediaConflict(
        Media $media,
        string $filePath,
        DateTimeInterface $fileModifiedAt,
        DateTimeInterface $lastSyncAt,
        ?string $field = null,
        ?string $flatValue = null,
        ?string $dbValue = null,
    ): array {
        // No conflict if file or DB was not modified since last sync
        if ($fileModifiedAt <= $lastSyncAt && $media->updatedAt <= $lastSyncAt) {
            return ['hasConflict' => false, 'winner' => null, 'conflictData' => null];
        }

        // Both modified since last sync = conflict
        if ($fileModifiedAt > $lastSyncAt && $media->updatedAt > $lastSyncAt) {
            $winner = $fileModifiedAt >= $media->updatedAt ? 'flat' : 'db';

            $conflictData = [
                'entityType' => 'media',
                'entityId' => $media->id,
                'winner' => $winner,
            ];
            if (null !== $field) {
                $conflictData['field'] = $field;
            }

            if (null !== $flatValue) {
                $conflictData['flatValue'] = $flatValue;
            }

            if (null !== $dbValue) {
                $conflictData['dbValue'] = $dbValue;
            }

            $this->logConflict('Media', (string) $media->id, $winner, null, $field);
            $this->stateManager->recordConflict($conflictData);

            return ['hasConflict' => true, 'winner' => $winner, 'conflictData' => $conflictData];
        }

        return ['hasConflict' => false, 'winner' => null, 'conflictData' => null];
    }

    /**
     * Create a backup file for a markdown document.
     */
    private function createMarkdownBackup(string $filePath, string $losingSource): ?string
    {
        if (! $this->filesystem->exists($filePath)) {
            return null;
        }

        $content = $this->filesystem->readFile($filePath);
        $pathInfo = pathinfo($filePath);
        $dirname = $pathInfo['dirname'] ?? '.';
        $extension = $pathInfo['extension'] ?? 'md';
        $backupFile = $dirname.'/'.$pathInfo['filename'].'~conflict-'.uniqid().'.'.$extension;

        // Add comment at the top explaining the conflict
        $header = \sprintf(
            "<!-- CONFLICT BACKUP: This file contains the %s version that lost to the %s version on %s -->\n\n",
            $losingSource,
            'flat' === $losingSource ? 'db' : 'flat',
            date('Y-m-d H:i:s'),
        );

        $this->filesystem->dumpFile($backupFile, $header.$content);

        return $backupFile;
    }

    /**
     * Record a CSV conflict to a conflicts file.
     *
     * @param array{entityType: string, entityId: int|string|null, field: string, flatValue: string, dbValue: string, winner: string} $conflictData
     */
    public function recordCsvConflict(string $csvFilePath, array $conflictData): void
    {
        $pathInfo = pathinfo($csvFilePath);
        $dirname = $pathInfo['dirname'] ?? '.';
        $conflictsFile = $dirname.'/'.$pathInfo['filename'].'.conflicts.csv';

        $isNew = ! $this->filesystem->exists($conflictsFile);

        $fp = fopen($conflictsFile, 'a');
        if (false === $fp) {
            return;
        }

        // Write header if new file
        if ($isNew) {
            fputcsv($fp, ['conflict_id', 'conflict_date', 'winner', 'entity_id', 'field', 'flat_value', 'db_value'], escape: '\\');
        }

        fputcsv(
            $fp,
            [
                uniqid('conflict_', true),
                date('Y-m-d H:i:s'),
                $conflictData['winner'],
                $conflictData['entityId'],
                $conflictData['field'],
                $conflictData['flatValue'],
                $conflictData['dbValue'],
            ],
            escape: '\\'
        );

        fclose($fp);

        $this->logConflict(
            $conflictData['entityType'],
            (string) $conflictData['entityId'],
            $conflictData['winner'],
            $conflictsFile,
            $conflictData['field'],
        );
    }

    /**
     * Find all unresolved conflict files for a host.
     *
     * @return string[]
     */
    public function findUnresolvedConflicts(?string $host = null): array
    {
        $contentDir = $this->contentDirFinder->get($host ?? '');

        $conflicts = [];

        // Find markdown conflict files (~conflict-*)
        $conflicts = [
            ...(glob($contentDir.'/**/*~conflict-*') ?: []),
            ...(glob($contentDir.'/*~conflict-*') ?: []),
        ];

        // Find non-empty CSV conflict files (*.conflicts.csv)
        $csvConflictFiles = [
            ...(glob($contentDir.'/**/*.conflicts.csv') ?: []),
            ...(glob($contentDir.'/*.conflicts.csv') ?: []),
        ];

        foreach ($csvConflictFiles as $file) {
            if (filesize($file) > 0) {
                $conflicts[] = $file;
            }
        }

        return array_unique($conflicts);
    }

    /**
     * Clear all conflict files for a host.
     *
     * @return string[] List of deleted files
     */
    public function clearConflictFiles(?string $host = null): array
    {
        $conflicts = $this->findUnresolvedConflicts($host);
        $deleted = [];

        foreach ($conflicts as $file) {
            if ($this->filesystem->exists($file)) {
                $this->filesystem->remove($file);
                $deleted[] = $file;
            }
        }

        return $deleted;
    }

    private function logConflict(
        string $entityType,
        string $entityId,
        string $winner,
        ?string $backupFile,
        ?string $field = null,
    ): void {
        $message = \sprintf(
            'Conflict detected on %s #%s%s - Winner: %s%s',
            $entityType,
            $entityId,
            null !== $field ? ' ('.$field.')' : '',
            $winner,
            null !== $backupFile ? ' - Backup: '.basename($backupFile) : '',
        );

        if (null !== $this->output) {
            $this->output->writeln('<comment>'.$message.'</comment>');
        }

        // Create admin notification with email alert
        $this->notificationService?->notifyConflict([
            'entityType' => $entityType,
            'entityId' => $entityId,
            'winner' => $winner,
            'backupFile' => $backupFile,
            'field' => $field,
        ], $this->currentHost);
    }
}
