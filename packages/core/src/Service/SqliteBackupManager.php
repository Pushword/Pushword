<?php

namespace Pushword\Core\Service;

use Symfony\Component\Filesystem\Filesystem;

final readonly class SqliteBackupManager
{
    public const int AUTOMATIC_RETENTION = 10;

    public function __construct(
        private Filesystem $filesystem,
        private string $varDir = 'var',
        private string $databaseUrl = 'sqlite:///var/app.db',
    ) {
    }

    public function isSupported(): bool
    {
        return str_starts_with($this->databaseUrl, 'sqlite:');
    }

    public function databaseExists(): bool
    {
        return $this->filesystem->exists($this->databaseFile());
    }

    public function create(): string
    {
        $backupFile = $this->databaseFile().'~'.date('YmdHis');
        $this->filesystem->copy($this->databaseFile(), $backupFile);
        $this->clean(self::AUTOMATIC_RETENTION);

        return $backupFile;
    }

    public function restoreLatest(): ?string
    {
        $backupFiles = $this->backupFiles();
        if ([] === $backupFiles) {
            return null;
        }

        rsort($backupFiles);
        $latestBackup = $backupFiles[0];
        $this->filesystem->copy($latestBackup, $this->databaseFile(), true);

        return $latestBackup;
    }

    /** @return string[] removed backup files */
    public function clean(int $keep = 1): array
    {
        $backupFiles = $this->backupFiles();
        rsort($backupFiles);
        $filesToDelete = \array_slice($backupFiles, $keep);

        foreach ($filesToDelete as $file) {
            $this->filesystem->remove($file);
        }

        return $filesToDelete;
    }

    /** @return string[] */
    public function backupFiles(): array
    {
        $backupFiles = glob($this->databaseFile().'~*');

        return false === $backupFiles ? [] : $backupFiles;
    }

    private function databaseFile(): string
    {
        return rtrim($this->varDir, '/').'/app.db';
    }
}
