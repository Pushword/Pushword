<?php

declare(strict_types=1);

namespace Pushword\Flat\Sync;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\MediaImporter;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

final class MediaSync
{
    private int $deletedCount = 0;

    private bool $storageImported = false;

    private ?OutputInterface $output = null;

    private ?Stopwatch $stopwatch = null;

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly MediaImporter $mediaImporter,
        private readonly MediaExporter $mediaExporter,
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SyncStateManager $stateManager,
        private readonly MediaStorageAdapter $mediaStorage,
        private readonly string $mediaDir,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
        $this->mediaExporter->setOutput($output);
    }

    public function setStopwatch(?Stopwatch $stopwatch): void
    {
        $this->stopwatch = $stopwatch;
    }

    public function sync(?string $host = null, bool $forceExport = false, ?string $exportDir = null): void
    {
        if (! $forceExport && $this->mustImport($host)) {
            $this->import($host);

            return;
        }

        $this->export($host, $forceExport, $exportDir);
    }

    public function import(?string $host = null): void
    {
        $this->deletedCount = 0;
        $app = $this->resolveApp($host);
        $contentDir = $this->contentDirFinder->get($app->getMainHost());
        $localMediaDir = $contentDir.'/media';
        $csvPath = $this->getCsvPath();

        // 1. Parse CSV index from content base dir
        $this->mediaImporter->resetIndex();
        $this->mediaImporter->loadIndex($csvPath);

        // 2. Validate files exist
        if ($this->mediaImporter->hasIndexData()) {
            $this->mediaImporter->validateFilesExist($this->mediaDir);

            if (file_exists($localMediaDir)) {
                $this->mediaImporter->validateFilesExist($localMediaDir);
            }
        }

        // 3. Delete media removed from CSV (before importing new files)
        $this->deleteMissingMedia();
        $this->mediaImporter->finishImport();

        // 4. Import storage files via Flysystem (works for both local and remote)
        // Storage is global (not per-host), so only scan and import once across all hosts
        if (! $this->storageImported) {
            $storageFiles = $this->collectStorageMediaFiles();
            $storageProgressBar = $this->createProgressBar(\count($storageFiles));
            $storageProgressBar?->start();

            foreach ($storageFiles as $fileName) {
                $storageProgressBar?->setMessage(basename($fileName));
                $lastModified = $this->mediaStorage->lastModified($fileName);
                $lastEditDateTime = new DateTime()->setTimestamp($lastModified);
                $this->mediaImporter->importFromStorage($fileName, $lastEditDateTime);
                $storageProgressBar?->advance();
            }

            if (null !== $storageProgressBar) {
                $storageProgressBar->finish();
                $this->output?->writeln('');
            }

            $this->storageImported = true;
        }

        // 5. Import local content dir files (flat content directory)
        $localFiles = file_exists($localMediaDir) ? $this->collectMediaFiles($localMediaDir) : [];
        $localProgressBar = $this->createProgressBar(\count($localFiles));
        $localProgressBar?->start();

        foreach ($localFiles as $path) {
            $localProgressBar?->setMessage(basename($path));
            $lastEditDateTime = new DateTime()->setTimestamp((int) filemtime($path));
            $this->mediaImporter->import($path, $lastEditDateTime);
            $localProgressBar?->advance();
        }

        if (null !== $localProgressBar) {
            $localProgressBar->finish();
            $this->output?->writeln('');
        }

        $this->mediaImporter->finishImport();

        // 6. Regenerate media.csv to reflect the current database state
        $this->regenerateIndex();

        // 7. Record import in sync state
        $this->stateManager->recordImport('media', $app->getMainHost());
    }

    /**
     * Collect all media files recursively from a directory.
     *
     * @return string[]
     */
    private function collectMediaFiles(string $dir): array
    {
        if (! file_exists($dir)) {
            return [];
        }

        $files = [];

        /** @var string[] $entries */
        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if (\in_array($entry, ['.', '..'], true)) {
                continue;
            }

            if ($this->isLockOrTempFile($entry)) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (is_dir($path)) {
                $files = [...$files, ...$this->collectMediaFiles($path)];

                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    /**
     * Collect media files from Flysystem storage (works for both local and remote).
     *
     * @return string[] Storage-relative file paths
     */
    private function collectStorageMediaFiles(): array
    {
        $this->output?->writeln('Collecting media files from storage...');

        $files = [];
        foreach ($this->mediaStorage->listContents('', true) as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $path = $item->path();

            if ($this->shouldSkipStorageFile($path)) {
                continue;
            }

            $files[] = $path;
        }

        $this->output?->writeln(\sprintf('Found %d media files', \count($files)));

        return $files;
    }

    /**
     * Regenerate media.csv after import to ensure it reflects current database state.
     */
    private function regenerateIndex(): void
    {
        $this->mediaExporter->csvDir = $this->contentDirFinder->getBaseDir();
        $this->mediaExporter->exportMedias();
    }

    public function export(?string $host = null, bool $force = false, ?string $exportDir = null): void
    {
        $app = $this->resolveApp($host);
        $targetDir = $exportDir ?? $this->contentDirFinder->get($app->getMainHost());

        $this->measure('media_export', function () use ($targetDir): void {
            $this->mediaExporter->csvDir = $this->contentDirFinder->getBaseDir();
            $this->mediaExporter->exportDir = $targetDir;
            $this->mediaExporter->exportMedias();
        });

        // Record export in sync state
        $this->stateManager->recordExport('media', $app->getMainHost());
    }

    public function mustImport(?string $host = null): bool
    {
        $this->output?->writeln('Detecting sync direction for media...');

        $app = $this->resolveApp($host);
        $contentDir = $this->contentDirFinder->get($app->getMainHost());
        $lastSyncTime = $this->stateManager->getLastSyncTime('media', $app->getMainHost());

        return array_any($this->getDirectoriesToScan($contentDir), fn (string $directory): bool => $this->hasNewerFiles($directory, $lastSyncTime));
    }

    /**
     * @return string[]
     */
    private function getDirectoriesToScan(string $contentDir): array
    {
        $dirs = [];

        if (file_exists($this->mediaDir) || ! $this->mediaStorage->isLocal()) {
            $dirs[] = $this->mediaDir;
        }

        if (file_exists($contentDir.'/media')) {
            $dirs[] = $contentDir.'/media';
        }

        return $dirs;
    }

    private function hasNewerFiles(string $dir, int $lastSyncTime = 0): bool
    {
        if ($dir === $this->mediaDir && ! $this->mediaStorage->isLocal()) {
            return $this->hasNewerStorageFiles($lastSyncTime);
        }

        if (! file_exists($dir)) {
            return false;
        }

        /** @var string[] $files */
        $files = scandir($dir);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'], true)) {
                continue;
            }

            if ($this->isLockOrTempFile($file)) {
                continue;
            }

            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                if ($this->hasNewerFiles($path, $lastSyncTime)) {
                    return true;
                }

                continue;
            }

            if ($this->isFileNewer($path)) {
                return true;
            }
        }

        return false;
    }

    private function hasNewerStorageFiles(int $lastSyncTime): bool
    {
        $progressBar = null;
        if (null !== $this->output) {
            $this->output->writeln('Scanning remote storage for changes...');
            $progressBar = new ProgressBar($this->output);
            $progressBar->setFormat(' %current% files scanned, %message%');
            $progressBar->setMessage('0 modified since last sync');
            $progressBar->start();
        }

        $scanned = 0;
        $checked = 0;
        $hasNewer = false;

        foreach ($this->mediaStorage->listContents('', true) as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $path = $item->path();

            if ($this->shouldSkipStorageFile($path)) {
                continue;
            }

            ++$scanned;
            if (0 === $scanned % 100) {
                $progressBar?->setProgress($scanned);
            }

            if ($lastSyncTime > 0 && $item->lastModified() <= $lastSyncTime) {
                continue;
            }

            ++$checked;
            $progressBar?->setMessage(\sprintf('%d modified since last sync', $checked));

            if ($this->isStorageFileNewer($path)) {
                $hasNewer = true;

                break;
            }
        }

        $progressBar?->setProgress($scanned);
        $progressBar?->finish();
        $this->output?->writeln('');

        return $hasNewer;
    }

    private function isStorageFileNewer(string $storagePath): bool
    {
        $media = $this->mediaRepository->findOneBy(['fileName' => $storagePath]);
        if (! $media instanceof Media) {
            return true;
        }

        return $this->isMediaHashDifferent($media, $this->mediaStorage->getLocalPath($storagePath));
    }

    private function isFileNewer(string $filePath): bool
    {
        $fileName = $this->extractMediaName($filePath);
        if ('' === $fileName) {
            return false;
        }

        $media = $this->mediaRepository->findOneBy(['fileName' => $fileName]);
        if (! $media instanceof Media) {
            return true;
        }

        return $this->isMediaHashDifferent($media, $filePath);
    }

    private function isMediaHashDifferent(Media $media, string $localPath): bool
    {
        $fileHash = sha1_file($localPath, true);
        $dbHash = $media->getHash();

        if (\is_resource($dbHash)) {
            $dbHash = stream_get_contents($dbHash);
        }

        return $fileHash !== $dbHash;
    }

    private function extractMediaName(string $filePath): string
    {
        $fileName = basename($filePath);

        // Legacy sidecar files support (for backward compatibility)
        if (
            str_ends_with($fileName, '.yaml')
            || str_ends_with($fileName, '.json')
        ) {
            return substr($fileName, 0, -5);
        }

        return $fileName;
    }

    private function shouldSkipStorageFile(string $path): bool
    {
        return $this->isLockOrTempFile(basename($path));
    }

    /**
     * Check if a file is a lock or temporary file that should be skipped.
     * Examples: .~lock.index.csv# (LibreOffice), ~$document.xlsx (MS Office).
     */
    private function isLockOrTempFile(string $fileName): bool
    {
        // Temp files starting with .~ (includes LibreOffice .~lock.filename#) or ~ (includes MS Office ~$filename)
        return str_starts_with($fileName, '.~') || str_starts_with($fileName, '~');
    }

    /**
     * Delete media that exist in DB but are missing from the CSV.
     * Only runs if the index CSV was loaded and contains fileNames.
     *
     * Must be called BEFORE importing new files to avoid deleting newly created media.
     */
    private function deleteMissingMedia(): void
    {
        // Only delete if we have index data (CSV was loaded)
        if (! $this->mediaImporter->hasIndexData()) {
            return;
        }

        $importedFileNames = $this->mediaImporter->getImportedFileNames();

        // If no fileNames in CSV, don't delete anything
        if ([] === $importedFileNames) {
            return;
        }

        // Find all media in DB and delete those not in CSV
        $allMedia = $this->mediaRepository->findAll();

        foreach ($allMedia as $media) {
            if (! \in_array($media->getFileName(), $importedFileNames, true)) {
                $this->logger?->info('Deleting media `'.$media->getFileName().'`');
                $this->output?->writeln(\sprintf('<comment>Deleting media %s</comment>', $media->getFileName()));
                ++$this->deletedCount;
                $this->entityManager->remove($media);
            }
        }

        $this->entityManager->flush();
    }

    private function getCsvPath(): string
    {
        return $this->contentDirFinder->getBaseDir().'/'.MediaExporter::CSV_FILE;
    }

    private function resolveApp(?string $host): SiteConfig
    {
        return null !== $host
            ? $this->apps->switchSite($host)->get()
            : $this->apps->get();
    }

    private function createProgressBar(int $count): ?ProgressBar
    {
        if (null === $this->output || 0 === $count) {
            return null;
        }

        $progressBar = new ProgressBar($this->output, $count);
        $progressBar->setMessage('');
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");

        return $progressBar;
    }

    private function measure(string $name, callable $operation): float
    {
        if (null !== $this->stopwatch) {
            $this->stopwatch->start($name);
            $operation();

            return $this->stopwatch->stop($name)->getDuration();
        }

        $start = microtime(true);
        $operation();

        return (microtime(true) - $start) * 1000;
    }

    /**
     * Get list of missing files detected during import validation.
     *
     * @return string[]
     */
    public function getMissingFiles(): array
    {
        return $this->mediaImporter->getMissingFiles();
    }

    public function getImportedCount(): int
    {
        return $this->mediaImporter->getImportedCount();
    }

    public function getSkippedCount(): int
    {
        return $this->mediaImporter->getSkippedCount();
    }

    public function getDeletedCount(): int
    {
        return $this->deletedCount;
    }

    public function getExportedCount(): int
    {
        return $this->mediaExporter->getExportedCount();
    }
}
