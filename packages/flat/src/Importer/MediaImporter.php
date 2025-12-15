<?php

namespace Pushword\Flat\Importer;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Flat\Exporter\MediaCsvHelper;
use Pushword\Flat\Exporter\MediaExporter;
use RuntimeException;

use function Safe\filesize;

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

    /** @var int[] IDs of media found in CSV (for deletion detection) */
    private array $importedIds = [];

    /** @var array<int, string> ID -> newFileName mapping for renames */
    private array $fileNameChanges = [];

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
        protected AppPool $apps,
        public string $mediaDir,
        public string $projectDir,
        private readonly MediaStorageAdapter $mediaStorage,
        private readonly ?LoggerInterface $logger = null,
    ) {
        parent::__construct($em, $apps);
    }

    private bool $newMedia = false;

    /**
     * Load the index.csv file from a directory.
     */
    public function loadIndex(string $dir): void
    {
        $indexPath = $dir.'/'.MediaExporter::INDEX_FILE;
        $reader = $this->createReader($indexPath);

        if (null === $reader) {
            return;
        }

        $header = $this->prepareHeader($reader);
        if ([] === $header) {
            return;
        }

        // Detect alt locale columns and custom columns
        $this->detectColumnTypes($header);

        $records = $reader->getRecords();
        foreach ($records as $row) {
            $fileName = $row['fileName'] ?? '';
            if ('' !== $fileName) {
                $this->indexData[$fileName] = $row;

                // Track IDs for deletion detection
                $id = $row['id'] ?? '';
                if ('' !== $id && is_numeric($id)) {
                    $intId = (int) $id;
                    $this->importedIds[] = $intId;

                    // Check if filename changed (for auto-rename)
                    $existingMedia = $this->em->getRepository(Media::class)->find($intId);
                    if ($existingMedia instanceof Media && $existingMedia->getFileName() !== $fileName) {
                        $this->fileNameChanges[$intId] = $fileName;
                    }
                }
            }
        }
    }

    /**
     * @return Reader<array<string, string|null>>|null
     */
    private function createReader(string $csvPath): ?Reader
    {
        if (! file_exists($csvPath)) {
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
        return array_values(array_filter($header, fn (string $col): bool => '' !== trim($col)));
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

            // Skip read-only columns (id, dimensions - auto-calculated, not imported)
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

    public function import(string $filePath, DateTimeInterface $lastEditDateTime): void
    {
        // Skip index.csv file itself
        if (str_ends_with($filePath, MediaExporter::INDEX_FILE)) {
            return;
        }

        // Skip legacy sidecar files (backward compatibility during transition)
        $isMetaDataFile = str_ends_with($filePath, '.json') || str_ends_with($filePath, '.yaml');
        if ($isMetaDataFile && file_exists(substr($filePath, 0, -5))) {
            return;
        }

        if ($this->isImage($filePath)) {
            $this->importImage($filePath, $lastEditDateTime);

            return;
        }

        $this->importMedia($filePath, $lastEditDateTime);
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

    public function importMedia(string $filePath, DateTimeInterface $dateTime): void
    {
        $fileName = $this->getFilename($filePath);
        $media = $this->getMediaFromIndex($fileName);

        if (! $this->newMedia && $media->getUpdatedAt() >= $dateTime) {
            ++$this->skippedCount;

            return; // no update needed
        }

        $this->logger?->info('Importing media {fileName}', ['fileName' => $fileName]);
        ++$this->importedCount;

        $filePath = $this->copyToMediaDir($filePath);

        $media
            ->setProjectDir($this->projectDir)
            ->setStoreIn(\dirname($filePath))
            ->setSize(filesize($filePath))
            ->setMimeType($this->getMimeTypeFromFile($filePath));

        // Update fileName if it changed (for existing media found by id)
        if ($media->getFileName() !== $fileName) {
            $media->setFileName($fileName);
        }

        $data = $this->getData($filePath);

        $this->setData($media, $data);
    }

    /**
     * Get data from index.csv for this file.
     *
     * @return array<string|int, mixed>
     */
    private function getData(string $filePath): array
    {
        $fileName = $this->getFilename($filePath);

        if (! isset($this->indexData[$fileName])) {
            return [];
        }

        return $this->parseIndexData($this->indexData[$fileName]);
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

        if ('' !== $this->mediaDir) {
            // Copy from local flat file to media storage
            $stream = fopen($filePath, 'r');
            if (false !== $stream) {
                $this->mediaStorage->writeStream($fileName, $stream);
                fclose($stream);
            }
        }

        // Return the local path for further processing (storeIn, size, mimeType)
        return $this->mediaStorage->getLocalPath($fileName);
    }

    /**
     * Get media from index data, looking up by id first if available.
     */
    protected function getMediaFromIndex(string $fileName): Media
    {
        $this->newMedia = false;

        // Check if we have index data for this file
        $indexData = $this->indexData[$fileName] ?? null;
        if (null !== $indexData) {
            $id = $indexData['id'] ?? '';
            if ('' !== $id && is_numeric($id)) {
                // Look up by ID first
                $mediaEntity = $this->em->getRepository(Media::class)->find((int) $id);
                if ($mediaEntity instanceof Media) {
                    return $mediaEntity;
                }
            }
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
        $mediaEntity = $this->em->getRepository(Media::class)->findOneBy(['fileName' => $media]);
        $this->newMedia = false;

        if (! $mediaEntity instanceof Media) {
            $this->newMedia = true;
            $mediaEntity = new Media();
            $mediaEntity
                ->setFileName($media)
                ->setAlt($media.' - '.uniqid());
        }

        return $mediaEntity;
    }

    public function resetIndex(): void
    {
        $this->indexData = [];
        $this->importedIds = [];
        $this->fileNameChanges = [];
        $this->altLocaleColumns = [];
        $this->customColumns = [];
        $this->missingFiles = [];
        $this->importedCount = 0;
        $this->skippedCount = 0;
    }

    /**
     * Get IDs of media found in CSV (for deletion detection).
     *
     * @return int[]
     */
    public function getImportedIds(): array
    {
        return $this->importedIds;
    }

    /**
     * Check if index has data (CSV was loaded).
     */
    public function hasIndexData(): bool
    {
        return [] !== $this->indexData;
    }

    /**
     * Prepare file renames based on CSV changes.
     * If CSV has a different fileName for an existing ID, rename the file in storage.
     *
     * @throws RuntimeException if a file cannot be renamed
     */
    public function prepareFileRenames(string $dir): void
    {
        foreach ($this->fileNameChanges as $mediaId => $newFileName) {
            $media = $this->em->getRepository(Media::class)->find($mediaId);
            if (! $media instanceof Media) {
                continue;
            }

            $media->setProjectDir($this->projectDir);
            $oldFileName = $media->getFileName();

            // Check if old file exists in storage
            if (! $this->mediaStorage->fileExists($oldFileName)) {
                // Maybe already renamed, check if new file exists
                if ($this->mediaStorage->fileExists($newFileName)) {
                    continue; // Already renamed
                }

                throw new RuntimeException(\sprintf('Cannot rename media #%d: file "%s" does not exist in storage', $mediaId, $oldFileName));
            }

            // Check if new path already exists (collision)
            if ($this->mediaStorage->fileExists($newFileName)) {
                throw new RuntimeException(\sprintf('Cannot rename media #%d from "%s" to "%s": target file already exists', $mediaId, $oldFileName, $newFileName));
            }

            // Rename the file in storage
            $this->mediaStorage->move($oldFileName, $newFileName);
        }
    }

    /**
     * Validate that files referenced in the CSV with IDs exist in storage or flat directory.
     * Only validates files that have IDs (existing media), not new files.
     * Missing files are logged as warnings and skipped instead of stopping the import.
     */
    public function validateFilesExist(string $dir): void
    {
        foreach ($this->indexData as $fileName => $data) {
            // Only validate files with IDs (existing media)
            // New media (empty ID) might not have files yet
            $id = $data['id'] ?? '';
            if ('' === $id) {
                continue;
            }

            if (! is_numeric($id)) {
                continue;
            }

            // Check flat directory first (source for import)
            $filePath = $dir.'/'.$fileName;
            if (file_exists($filePath)) {
                continue;
            }

            // Check storage (already imported)
            if ($this->mediaStorage->fileExists($fileName)) {
                continue;
            }

            // Check if this is a renamed file (old file might exist in storage)
            $media = $this->em->getRepository(Media::class)->find((int) $id);
            if ($media instanceof Media) {
                $media->setProjectDir($this->projectDir);
                $oldFileName = $media->getFileName();
                // Check if old file exists in flat dir or storage
                if (file_exists($dir.'/'.$oldFileName)) {
                    // File exists with old name, will be renamed
                    continue;
                }

                if ($this->mediaStorage->fileExists($oldFileName)) {
                    // File exists with old name, will be renamed
                    continue;
                }
            }

            // Log warning and skip instead of throwing exception
            $warningMessage = \sprintf('Media file "%s" referenced in CSV does not exist in "%s" or storage - skipping', $fileName, $dir);
            $this->logger?->warning($warningMessage);
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
