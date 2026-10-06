<?php

declare(strict_types=1);

namespace Pushword\Flat\Sync;

use DateTimeInterface;
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

    public function __construct(
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly SyncStateManager $stateManager,
        private readonly ?AdminNotificationService $notificationService = null,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
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
        ?string $fileContent = null,
        ?string $dbContent = null,
    ): array {
        // No conflict if file or DB was not modified since last sync
        if ($fileModifiedAt <= $lastSyncAt && $page->updatedAt <= $lastSyncAt) {
            return ['hasConflict' => false, 'winner' => null, 'backupFile' => null];
        }

        // Both modified since last sync = potential conflict
        if ($fileModifiedAt > $lastSyncAt && $page->updatedAt > $lastSyncAt) {
            // No real conflict if content is identical despite timestamp divergence
            if (null !== $fileContent && null !== $dbContent && $fileContent === $dbContent) {
                return ['hasConflict' => false, 'winner' => null, 'backupFile' => null];
            }

            // Most recent wins
            $winner = $fileModifiedAt >= $page->updatedAt ? 'flat' : 'db';

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
     * Find all unresolved conflict files for a host.
     *
     * @return string[]
     */
    public function findUnresolvedConflicts(?string $host = null): array
    {
        $contentDir = $this->contentDirFinder->get($host ?? '');

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

    private function logConflict(string $entityType, string $entityId, string $winner, ?string $backupFile): void
    {
        $message = \sprintf(
            'Conflict detected on %s #%s - Winner: %s%s',
            $entityType,
            $entityId,
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
        ]);
    }
}
