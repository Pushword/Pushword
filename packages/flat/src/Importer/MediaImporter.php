<?php

namespace Pushword\Flat\Importer;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Flat\Exporter\MediaCsvHelper;
use Pushword\Flat\Exporter\MediaExporter;

use function Safe\filesize;

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

    /** @var int[] IDs of media found in CSV (for deletion detection) */
    private array $importedIds = [];

    /** @var string[] */
    private array $altLocaleColumns = [];

    /** @var string[] */
    private array $customColumns = [];

    public function __construct(
        protected EntityManagerInterface $em,
        protected AppPool $apps,
        public string $mediaDir,
        public string $projectDir,
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
                    $this->importedIds[] = (int) $id;
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
        return false !== getimagesize($filePath);
    }

    public function importMedia(string $filePath, DateTimeInterface $dateTime): void
    {
        $media = $this->getMedia($this->getFilename($filePath));

        if (! $this->newMedia && $media->getUpdatedAt() >= $dateTime) {
            return; // no update needed
        }

        $filePath = $this->copyToMediaDir($filePath);

        $media
            ->setProjectDir($this->projectDir)
            ->setStoreIn(\dirname($filePath))
            ->setSize(filesize($filePath))
            ->setMimeType($this->getMimeTypeFromFile($filePath));

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
        $newFilePath = $this->mediaDir.'/'.$this->getFilename($filePath);

        if ('' !== $this->mediaDir && $filePath !== $newFilePath) {
            (new Filesystem())->copy($filePath, $newFilePath);

            return $newFilePath;
        }

        return $filePath;
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
        $this->altLocaleColumns = [];
        $this->customColumns = [];
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
}
