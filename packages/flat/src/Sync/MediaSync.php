<?php

namespace Pushword\Flat\Sync;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Flat\Exporter\MediaExporter;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\MediaImporter;

use function Safe\filemtime;
use function Safe\scandir;

use Symfony\Component\Stopwatch\Stopwatch;

final readonly class MediaSync
{
    public function __construct(
        private AppPool $apps,
        private FlatFileContentDirFinder $contentDirFinder,
        private MediaImporter $mediaImporter,
        private MediaExporter $mediaExporter,
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
        private string $mediaDir,
        private ?Stopwatch $stopwatch = null,
    ) {
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
        $app = $this->resolveApp($host);
        $contentDir = $this->contentDirFinder->get($app->getMainHost());
        $localMediaDir = $contentDir.'/media';

        // 1. Parse CSV indexes
        $this->mediaImporter->resetIndex();
        $this->mediaImporter->loadIndex($this->mediaDir);
        // if (file_exists($localMediaDir)) {
        //     $this->mediaImporter->loadIndex($localMediaDir);
        // }

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

        // 4. Import/update media files (including new files not in CSV)
        $this->importDirectory($this->mediaDir);
        $this->mediaImporter->finishImport();

        $this->importDirectory($localMediaDir);
        $this->mediaImporter->finishImport();
    }

    public function export(?string $host = null, bool $force = false, ?string $exportDir = null): void
    {
        $app = $this->resolveApp($host);
        $targetDir = $exportDir ?? $this->contentDirFinder->get($app->getMainHost());

        $this->measure('media_export', function () use ($targetDir): void {
            $this->mediaExporter->exportDir = $targetDir;
            $this->mediaExporter->exportMedias();
        });
    }

    public function mustImport(?string $host = null): bool
    {
        $app = $this->resolveApp($host);
        $contentDir = $this->contentDirFinder->get($app->getMainHost());

        foreach ($this->getDirectoriesToScan($contentDir) as $directory) {
            if ($this->hasNewerFiles($directory)) {
                return true;
            }
        }

        return false;
    }

    private function importDirectory(string $dir): void
    {
        if (! file_exists($dir)) {
            return;
        }

        /** @var string[] $files */
        $files = scandir($dir);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'], true)) {
                continue;
            }

            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->importDirectory($path);

                continue;
            }

            $lastEditDateTime = (new DateTime())->setTimestamp(filemtime($path));
            $this->mediaImporter->import($path, $lastEditDateTime);
        }
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

        $lastEditDateTime = (new DateTime())->setTimestamp(filemtime($filePath));

        return $lastEditDateTime > $media->safegetUpdatedAt();
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
            $mediaId = $media->getId();
            if (null !== $mediaId && ! \in_array($mediaId, $importedIds, true)) {
                // Media exists in DB but not in CSV - delete it
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
}
