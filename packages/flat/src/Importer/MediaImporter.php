<?php

namespace Pushword\Flat\Importer;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Override;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ThumbnailGenerator;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Exporter\MediaCsvHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Permit to find error in image or link.
 *
 * @extends AbstractImporter<Media>
 */
class MediaImporter extends AbstractImporter
{
    use ImageImporterTrait;

    /** @var array<string, array<string, string|null>> */
    private array $indexData = [];

    /** @var string[] */
    private array $altLocaleColumns = [];

    /** @var string[] */
    private array $customColumns = [];

    /** @var string[] */
    private array $missingFiles = [];

    private int $importedCount = 0;

    private int $skippedCount = 0;

    public function __construct(
        protected EntityManagerInterface $em,
        protected SiteRegistry $apps,
        public string $mediaDir,
        public string $projectDir,
        private readonly MediaStorageAdapter $mediaStorage,
        private readonly ThumbnailGenerator $thumbnailGenerator,
        private readonly ImageCacheManager $imageCacheManager,
        private readonly ?LoggerInterface $logger = null,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct($em, $apps);
    }

    private bool $newMedia = false;

    private bool $duplicateSkipped = false;

    /**
     * Load the media.csv file from a local directory path.
     */
    public function loadIndex(string $csvPath): void
    {
        $reader = $this->createReader($csvPath);

        if (null === $reader) {
            return;
        }

        $this->processReader($reader);
    }

    /**
     * @param Reader<array<string, string|null>> $reader
     */
    private function processReader(Reader $reader): void
    {
        $header = $this->prepareHeader($reader);
        if ([] === $header) {
            return;
        }

        // Detect alt locale columns and custom columns
        $this->detectColumnTypes($header);

        $records = $reader->getRecords();
        foreach ($records as $row) {
            $fileName = $row['fileName'] ?? '';
            if ('' === $fileName) {
                continue;
            }

            if (isset($this->indexData[$fileName])) {
                $this->logger?->warning('Duplicate fileName "{fileName}" in media.csv - keeping first occurrence, skipping duplicate', [
                    'fileName' => $fileName,
                ]);

                continue;
            }

            $this->indexData[$fileName] = $row;
        }
    }

    /**
     * @return Reader<array<string, string|null>>|null
     */
    private function createReader(string $csvPath): ?Reader
    {
        if (! $this->filesystem->exists($csvPath)) {
            return null;
        }

        try {
            $reader = Reader::from($csvPath, 'r');
        } catch (CsvException) {
            return null;
        }

        try {
            $reader->setHeaderOffset(0);
        } catch (CsvException) {
            return null;
        }

        return $reader;
    }

    /**
     * @param Reader<array<string, string|null>> $reader
     *
     * @return string[]
     */
    private function prepareHeader(Reader $reader): array
    {
        /** @var string[] $header */
        $header = $reader->getHeader();
        if ([] === $header) {
            return [];
        }

        // Filter empty columns
        return array_values(array_filter($header, static fn (string $col): bool => '' !== trim($col)));
    }

    /**
     * @param string[] $headers
     */
    private function detectColumnTypes(array $headers): void
    {
        foreach ($headers as $column) {
            // Skip base columns
            if (\in_array($column, MediaCsvHelper::BASE_COLUMNS, true)) {
                continue;
            }

            // Skip read-only columns (dimensions - auto-calculated, not imported)
            if (\in_array($column, MediaCsvHelper::READ_ONLY_COLUMNS, true)) {
                continue;
            }

            if (MediaCsvHelper::isAltColumn($column)) {
                if (! \in_array($column, $this->altLocaleColumns, true)) {
                    $this->altLocaleColumns[] = $column;
                }
            } elseif (! \in_array($column, $this->customColumns, true)) {
                $this->customColumns[] = $column;
            }
        }
    }

    #[Override]
    public function import(string $filePath, DateTimeInterface $lastEditDateTime): bool
    {
        // Skip legacy sidecar files (backward compatibility during transition)
        $isMetaDataFile = str_ends_with($filePath, '.json') || str_ends_with($filePath, '.yaml');
        if ($isMetaDataFile && $this->filesystem->exists(substr($filePath, 0, -5))) {
            return false;
        }

        if ($this->isImage($filePath)) {
            return $this->importImage($filePath, $lastEditDateTime);
        }

        return $this->importMedia($filePath, $lastEditDateTime);
    }

    /**
     * Import a file that already exists in Flysystem storage.
     * Unlike import(), this skips copyToMediaDir() since the file is already in storage.
     */
    public function importFromStorage(string $fileName, DateTimeInterface $lastEditDateTime): bool
    {
        // Skip legacy sidecar files
        $isMetaDataFile = str_ends_with($fileName, '.json') || str_ends_with($fileName, '.yaml');
        if ($isMetaDataFile && $this->mediaStorage->fileExists(substr($fileName, 0, -5))) {
            return false;
        }

        $localPath = $this->mediaStorage->getLocalPath($fileName);

        if ($this->isImage($localPath)) {
            return $this->importImageFromStorage($fileName, $localPath, $lastEditDateTime);
        }

        return $this->importMediaFromStorage($fileName, $localPath, $lastEditDateTime);
    }

    private function importMediaFromStorage(string $fileName, string $localPath, DateTimeInterface $dateTime): bool
    {
        $media = $this->getMediaFromIndex($fileName);

        return $this->doImportMedia($media, $fileName, $localPath, $this->mediaStorage->getMediaDir(), $dateTime);
    }

    private function doImportMedia(Media $media, string $fileName, string $localPath, string $storeIn, DateTimeInterface $dateTime): bool
    {
        if ($this->duplicateSkipped) {
            $this->duplicateSkipped = false;
            ++$this->skippedCount;

            return false;
        }

        if (! $this->newMedia && ! $this->hasFileContentChanged($localPath, $media)) {
            ++$this->skippedCount;

            return false;
        }

        $this->logger?->info('Importing media `'.$fileName.'` ('.($this->newMedia ? 'new' : $media->id).')');
        ++$this->importedCount;

        $media
            ->setProjectDir($this->projectDir)
            ->setStoreIn($storeIn)
            ->setSize((int) filesize($localPath))
            ->setMimeType($this->getMimeTypeFromFile($localPath))
            ->resetHash();

        if ($media->getFileName() !== $fileName) {
            $media->setFileName($fileName);
        }

        $data = $this->getDataForFileName($fileName);
        $this->setData($media, $data);
        $media->updatedAt = $dateTime;

        if (! $media->isImage()) {
            $this->imageCacheManager->ensurePublicSymlink($media);
        }

        return true;
    }

    private function importImageFromStorage(string $fileName, string $localPath, DateTimeInterface $dateTime): bool
    {
        $media = $this->getMedia($fileName);

        if ($this->duplicateSkipped) {
            $this->duplicateSkipped = false;
            ++$this->skippedCount;

            return false;
        }

        if (! $this->newMedia && ! $this->hasFileContentChanged($localPath, $media)) {
            ++$this->skippedCount;

            return false;
        }

        $this->logger?->info('Importing media `'.$fileName.'` ('.($this->newMedia ? 'new' : $media->id).')');
        ++$this->importedCount;

        $this->importImageMediaData($media, $localPath, $this->mediaStorage->getMediaDir(), $fileName);

        return true;
    }

    /**
     * Get data from media.csv for a given fileName (not a file path).
     *
     * @return array<string|int, mixed>
     */
    private function getDataForFileName(string $fileName): array
    {
        if (! isset($this->indexData[$fileName])) {
            return [];
        }

        return $this->parseIndexData($this->indexData[$fileName]);
    }

    private function isImage(string $filePath): bool
    {
        $mimeType = $this->getMimeTypeFromFile($filePath);

        // SVG files are images but getimagesize() doesn't support them
        if ('image/svg+xml' === $mimeType) {
            return false;
        }

        if (! str_starts_with($mimeType, 'image/')) {
            return false;
        }

        return false !== @getimagesize($filePath);
    }

    public function importMedia(string $filePath, DateTimeInterface $dateTime): bool
    {
        $fileName = $this->getFilename($filePath);
        $media = $this->getMediaFromIndex($fileName);
        $filePath = $this->copyToMediaDir($filePath);

        return $this->doImportMedia($media, $fileName, $filePath, \dirname($filePath), $dateTime);
    }

    private function hasFileContentChanged(string $filePath, Media $media): bool
    {
        return sha1_file($filePath, true) !== $this->getMediaHash($media);
    }

    /**
     * Normalize media hash from database (may be stored as resource in SQLite).
     */
    private function getMediaHash(Media $media): ?string
    {
        $hash = $media->getHash();
        if (\is_resource($hash)) {
            $hash = stream_get_contents($hash);
        }

        return \is_string($hash) ? $hash : null;
    }

    /**
     * Get data from media.csv for this file.
     *
     * @return array<string|int, mixed>
     */
    private function getData(string $filePath): array
    {
        return $this->getDataForFileName($this->getFilename($filePath));
    }

    /**
     * @param array<string, string|null> $data
     *
     * @return array<string, mixed>
     */
    private function parseIndexData(array $data): array
    {
        /** @var array<string, mixed> $result */
        $result = [];

        // Extract base alt
        $altValue = $data['alt'] ?? null;
        if (\is_string($altValue) && '' !== trim($altValue)) {
            $result['alt'] = trim($altValue);
        }

        // Extract tags
        $tagsValue = $data['tags'] ?? null;
        if (\is_string($tagsValue) && '' !== trim($tagsValue)) {
            $result['tags'] = trim($tagsValue);
        }

        // Extract localized alts into 'alts' as YAML
        $alts = $this->extractLocalizedAlts($data);
        if ([] !== $alts) {
            $result['alts'] = Yaml::dump($alts);
        }

        // Extract custom properties
        foreach ($this->customColumns as $column) {
            $columnValue = $data[$column] ?? null;
            if (! \is_string($columnValue)) {
                continue;
            }

            $value = trim($columnValue);
            if ('' === $value) {
                continue;
            }

            $result[$column] = MediaCsvHelper::decodeValue($value);
        }

        return $result;
    }

    /**
     * @param array<string, string|null> $data
     *
     * @return array<string, string>
     */
    private function extractLocalizedAlts(array $data): array
    {
        $alts = [];

        foreach ($this->altLocaleColumns as $column) {
            $columnValue = $data[$column] ?? null;
            if (! \is_string($columnValue)) {
                continue;
            }

            $value = trim($columnValue);
            if ('' === $value) {
                continue;
            }

            $locale = MediaCsvHelper::getLocaleFromAltColumn($column);
            $alts[$locale] = $value;
        }

        return $alts;
    }

    /**
     * @param array<mixed> $data
     */
    private function setData(Media $media, array $data): void
    {
        $media->setCustomProperties([]);

        foreach ($data as $key => $value) {
            $key = self::underscoreToCamelCase((string) $key);

            $setter = 'set'.ucfirst($key);
            if (method_exists($media, $setter)) {
                if (\in_array($key, ['createdAt', 'updatedAt', 'fileName'], true)) {
                    continue;
                }

                $media->$setter($value); // @phpstan-ignore-line

                continue;
            }

            $media->setCustomProperty($key, $this->sanitizeUtf8($value));
        }

        if ($this->newMedia) {
            $this->em->persist($media);
        }
    }

    public function getFilename(string $filePath): string
    {
        return str_replace(\dirname($filePath).'/', '', $filePath);
    }

    private function copyToMediaDir(string $filePath): string
    {
        $fileName = $this->getFilename($filePath);
        $localPath = $this->mediaStorage->getLocalPath($fileName);

        // Skip copy if source and destination are the same file (already in media dir)
        // Otherwise writeStream would truncate the file before reading, corrupting it
        if ('' !== $this->mediaDir && realpath($filePath) !== realpath($localPath)) {
            $stream = fopen($filePath, 'r');
            if (false !== $stream) {
                $this->mediaStorage->writeStream($fileName, $stream);
                fclose($stream);
            }

            // Clear PHP's stat cache to ensure getimagesize() sees the freshly written file
            clearstatcache(true, $localPath);
        }

        return $localPath;
    }

    /**
     * Get media from index data, looking up by fileName.
     */
    protected function getMediaFromIndex(string $fileName): Media
    {
        $this->newMedia = false;

        $mediaEntity = $this->em->getRepository(Media::class)->findOneBy(['fileName' => $fileName]);
        if ($mediaEntity instanceof Media) {
            return $mediaEntity;
        }

        $match = $this->findExistingMediaByFileHash($fileName);
        if ($match instanceof Media) {
            return $match;
        }

        // Create new media
        $this->newMedia = true;
        $mediaEntity = new Media();
        $mediaEntity
            ->setFileName($fileName)
            ->setAlt($fileName.' - '.uniqid());

        return $mediaEntity;
    }

    protected function getMedia(string $media): Media
    {
        return $this->getMediaFromIndex($media);
    }

    /**
     * Check if a file exists in storage and try to match it to an existing media by hash.
     */
    private function findExistingMediaByFileHash(string $fileName): ?Media
    {
        if (! $this->mediaStorage->fileExists($fileName)) {
            return null;
        }

        $localPath = $this->mediaStorage->getLocalPath($fileName);
        if (! file_exists($localPath)) {
            return null;
        }

        return $this->findMediaByHash($localPath, $fileName);
    }

    /**
     * Find an existing media entity by SHA-1 hash match.
     *
     * First checks media in the missingFiles list (renamed files).
     * Then checks all existing media (duplicate detection — new file is deleted, history updated).
     */
    private function findMediaByHash(string $filePath, string $newFileName): ?Media
    {
        $fileHash = sha1_file($filePath, true);
        if (false === $fileHash) {
            return null;
        }

        // 1. Check missing files (renamed file detection)
        foreach ($this->missingFiles as $key => $missingFileName) {
            $missingMedia = $this->em->getRepository(Media::class)->findOneBy(['fileName' => $missingFileName]);
            if (! $missingMedia instanceof Media) {
                continue;
            }

            if ($fileHash === $this->getMediaHash($missingMedia)) {
                $this->logger?->info(\sprintf('Hash match: renamed file "%s" matches missing media "%s" (id: %s)', $newFileName, $missingFileName, $missingMedia->id));
                $missingMedia->setFileName($newFileName);
                unset($this->missingFiles[$key]);
                $this->missingFiles = array_values($this->missingFiles);

                return $missingMedia;
            }
        }

        // 2. Check all existing media (duplicate detection)
        $allMedia = $this->em->getRepository(Media::class)->findAll();
        foreach ($allMedia as $existingMedia) {
            if ($existingMedia->getFileName() === $newFileName) {
                continue; // Skip self-match
            }

            if ($fileHash === $this->getMediaHash($existingMedia)) {
                $this->logger?->info(\sprintf('Duplicate detected: "%s" has same hash as existing media "%s" (id: %s) — deleting duplicate', $newFileName, $existingMedia->getFileName(), $existingMedia->id));
                $existingMedia->addFileNameToHistory($newFileName);

                // Delete the duplicate file
                if ($this->mediaStorage->fileExists($newFileName)) {
                    $this->mediaStorage->delete($newFileName);
                } elseif (file_exists($filePath)) {
                    @unlink($filePath);
                }

                $this->newMedia = false;
                $this->duplicateSkipped = true;

                return $existingMedia;
            }
        }

        return null;
    }

    public function resetIndex(): void
    {
        $this->indexData = [];
        $this->altLocaleColumns = [];
        $this->customColumns = [];
        $this->missingFiles = [];
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->duplicateSkipped = false;
    }

    /**
     * Get fileNames of media found in CSV (for deletion detection).
     *
     * @return string[]
     */
    public function getImportedFileNames(): array
    {
        return array_keys($this->indexData);
    }

    /**
     * Check if index has data (CSV was loaded).
     */
    public function hasIndexData(): bool
    {
        return [] !== $this->indexData;
    }

    /**
     * Validate that files referenced in the CSV exist in storage or flat directory.
     * Missing files are logged as warnings and skipped instead of stopping the import.
     */
    public function validateFilesExist(string $dir): void
    {
        foreach (array_keys($this->indexData) as $fileName) {
            // Check flat directory first (source for import)
            if ($this->filesystem->exists($dir.'/'.$fileName)) {
                continue;
            }

            // Check storage (already imported)
            if ($this->mediaStorage->fileExists($fileName)) {
                continue;
            }

            // Log warning and skip instead of throwing exception
            $this->logger?->warning(\sprintf('Media file "%s" referenced in CSV does not exist in "%s" or storage - skipping', $fileName, $dir));
            $this->missingFiles[] = $fileName;

            // Remove from index to prevent import attempt
            unset($this->indexData[$fileName]);
        }
    }

    /**
     * Get list of missing files detected during validation.
     *
     * @return string[]
     */
    public function getMissingFiles(): array
    {
        return $this->missingFiles;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }
}
