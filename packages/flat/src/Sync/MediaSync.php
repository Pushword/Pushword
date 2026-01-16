<?php

declare(strict_types=1);

namespace Pushword\Flat\Sync;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\MediaImporter;

use function Safe\filemtime;
use function Safe\scandir;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

final class MediaSync
{
    private int $deletedCount = 0;

    private ?OutputInterface $output = null;

    private ?Stopwatch $stopwatch = null;

    public function __construct(
        private readonly AppPool $apps,
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly MediaImporter $mediaImporter,
        private readonly MediaExporter $mediaExporter,
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SyncStateManager $stateManager,
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

        // 1. Parse CSV indexes
        $this->mediaImporter->resetIndex();
        $this->mediaImporter->loadIndex($this->mediaDir);

        // 2. Validate files exist and prepare renames
        if ($this->mediaImporter->hasIndexData()) {
            $this->mediaImporter->validateFilesExist($this->mediaDir);
            $this->mediaImporter->prepareFileRenames($this->mediaDir);

            if (file_exists($localMediaDir)) {
                $this->mediaImporter->validateFilesExist($localMediaDir);
                $this->mediaImporter->prepareFileRenames($localMediaDir);
            }
        }

        // 3. Delete media removed from CSV (before importing new files)
        $this->deleteMissingMedia();
        $this->mediaImporter->finishImport();

        // 4. Collect all media files
        $files = $this->collectMediaFiles($this->mediaDir);
        $localFiles = file_exists($localMediaDir) ? $this->collectMediaFiles($localMediaDir) : [];
        $allFiles = [...$files, ...$localFiles];

        // 5. Import/update media files (only show actually imported files)
        foreach ($allFiles as $path) {
            $lastEditDateTime = new DateTime()->setTimestamp(filemtime($path));
            $imported = $this->mediaImporter->import($path, $lastEditDateTime);

            if ($imported) {
                $fileName = basename($path);
                $this->output?->writeln(\sprintf('Imported %s', $fileName));
            }
        }

        $this->mediaImporter->finishImport();

        // 6. Regenerate index.csv to reflect the current database state
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

            // Skip index.csv
            if (MediaExporter::INDEX_FILE === $entry) {
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
     * Regenerate index.csv after import to ensure it reflects current database state.
     */
    private function regenerateIndex(): void
    {
        $this->mediaExporter->exportMedias();
    }

    public function export(?string $host = null, bool $force = false, ?string $exportDir = null): void
    {
        $app = $this->resolveApp($host);
        $targetDir = $exportDir ?? $this->contentDirFinder->get($app->getMainHost());

        $this->measure('media_export', function () use ($targetDir): void {
            $this->mediaExporter->exportDir = $targetDir;
            $this->mediaExporter->exportMedias();
        });

        // Record export in sync state
        $this->stateManager->recordExport('media', $app->getMainHost());
    }

    public function mustImport(?string $host = null): bool
    {
        $app = $this->resolveApp($host);
        $contentDir = $this->contentDirFinder->get($app->getMainHost());

        return array_any($this->getDirectoriesToScan($contentDir), fn (string $directory): bool => $this->hasNewerFiles($directory));
    }

    /**
     * @return string[]
     */
    private function getDirectoriesToScan(string $contentDir): array
    {
        return array_values(array_filter([
            $this->mediaDir,
            $contentDir.'/media',
        ], file_exists(...)));
    }

    private function hasNewerFiles(string $dir): bool
    {
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
                if ($this->hasNewerFiles($path)) {
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

        // Use hash comparison instead of mtime to detect real content changes
        $fileHash = sha1_file($filePath, true);
        $dbHash = $media->getHash();

        // Handle binary hash from database (may be a resource or string)
        if (\is_resource($dbHash)) {
            $dbHash = stream_get_contents($dbHash);
        }

        return $fileHash !== $dbHash;
    }

    private function extractMediaName(string $filePath): string
    {
        $fileName = basename($filePath);

        // Skip index.csv when checking for newer files
        if (MediaExporter::INDEX_FILE === $fileName) {
            return '';
        }

        // Legacy sidecar files support (for backward compatibility)
        if (
            str_ends_with($fileName, '.yaml')
            || str_ends_with($fileName, '.json')
        ) {
            return substr($fileName, 0, -5);
        }

        return $fileName;
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
     * Only runs if the index CSV was loaded and contains IDs.
     *
     * Must be called BEFORE importing new files to avoid deleting newly created media.
     */
    private function deleteMissingMedia(): void
    {
        // Only delete if we have index data (CSV was loaded)
        if (! $this->mediaImporter->hasIndexData()) {
            return;
        }

        $importedIds = $this->mediaImporter->getImportedIds();

        // If no IDs in CSV, don't delete anything
        // This handles the case of fresh imports or CSVs with only new media
        if ([] === $importedIds) {
            return;
        }

        // Find all media in DB and delete those not in CSV
        $allMedia = $this->mediaRepository->findAll();

        foreach ($allMedia as $media) {
            $mediaId = $media->id;
            if (null !== $mediaId && ! \in_array($mediaId, $importedIds, true)) {
                $this->logger?->info('Deleting media `'.$media->getFileName().'`');
                $this->output?->writeln(\sprintf('<comment>Deleting media %s</comment>', $media->getFileName()));
                ++$this->deletedCount;
                $this->entityManager->remove($media);
            }
        }

        $this->entityManager->flush();
    }

    private function resolveApp(?string $host): AppConfig
    {
        return null !== $host
            ? $this->apps->switchCurrentApp($host)->get()
            : $this->apps->get();
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
