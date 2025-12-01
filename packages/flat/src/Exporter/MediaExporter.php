<?php

namespace Pushword\Flat\Exporter;

use Exception;
use League\Csv\Writer;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Filesystem\Filesystem;

final class MediaExporter
{
    public const string INDEX_FILE = 'index.csv';

    public string $copyMedia = '';

    public string $exportDir = '';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly MediaRepository $mediaRepo,
        private readonly string $mediaDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function exportMedias(): void
    {
        $medias = $this->mediaRepo->findAll();

        if ([] === $medias) {
            return;
        }

        $altLocaleColumns = $this->collectAltLocaleColumns($medias);
        $customColumns = $this->collectCustomColumns($medias);
        $header = array_merge(
            MediaCsvHelper::BASE_COLUMNS,
            MediaCsvHelper::DIMENSION_COLUMNS,
            ['fileNameHistory'],
            $altLocaleColumns,
            $customColumns,
        );

        /** @var array<int, array<string, string|null>> $rows */
        $rows = [];
        foreach ($medias as $media) {
            $this->copyMediaFile($media);
            $row = $this->buildRow($media, $altLocaleColumns, $customColumns);

            // Reorder values in header order for consistency
            $orderedRow = [];
            foreach ($header as $column) {
                $orderedRow[$column] = $row[$column] ?? null;
            }

            $rows[] = $orderedRow;
        }

        $csvFilePath = $this->getCsvFilePath();
        $this->ensureDirectoryExists(\dirname($csvFilePath));

        $writer = Writer::from($csvFilePath, 'w+');
        $writer->insertOne($header);
        $writer->insertAll($rows);
    }

    /**
     * @param Media[] $medias
     *
     * @return string[]
     */
    private function collectAltLocaleColumns(array $medias): array
    {
        $locales = [];
        foreach ($medias as $media) {
            $alts = $media->getAltsParsed();
            foreach (array_keys($alts) as $locale) {
                $locales[$locale] = true;
            }
        }

        $columns = [];
        foreach (array_keys($locales) as $locale) {
            $columns[] = MediaCsvHelper::buildAltColumnName($locale);
        }

        sort($columns);

        return $columns;
    }

    /**
     * @param Media[] $medias
     *
     * @return string[]
     */
    private function collectCustomColumns(array $medias): array
    {
        $columns = [];
        foreach ($medias as $media) {
            /** @var array<string, mixed> $customProperties */
            $customProperties = $media->getCustomProperties();
            foreach (array_keys($customProperties) as $property) {
                $columns[$property] = true;
            }
        }

        $columns = array_keys($columns);
        sort($columns);

        return $columns;
    }

    /**
     * @param string[] $altLocaleColumns
     * @param string[] $customColumns
     *
     * @return array<string, string|null>
     */
    private function buildRow(Media $media, array $altLocaleColumns, array $customColumns): array
    {
        $fileNameHistory = $media->getFileNameHistory();
        $row = [
            'id' => null !== $media->getId() ? (string) $media->getId() : '',
            'fileName' => $media->getFileName(),
            'alt' => $media->getAlt(true),
            'width' => null !== $media->getWidth() ? (string) $media->getWidth() : '',
            'height' => null !== $media->getHeight() ? (string) $media->getHeight() : '',
            'ratio' => null !== $media->getRatio() ? (string) $media->getRatio() : '',
            'fileNameHistory' => [] !== $fileNameHistory ? implode(',', $fileNameHistory) : '',
        ];

        // Add localized alts
        $alts = $media->getAltsParsed();
        foreach ($altLocaleColumns as $column) {
            $locale = MediaCsvHelper::getLocaleFromAltColumn($column);
            $row[$column] = $alts[$locale] ?? '';
        }

        // Add custom properties
        /** @var array<string, mixed> $customProperties */
        $customProperties = $media->getCustomProperties();
        foreach ($customColumns as $column) {
            $row[$column] = array_key_exists($column, $customProperties)
                ? MediaCsvHelper::encodeValue($customProperties[$column])
                : '';
        }

        return $row;
    }

    private function copyMediaFile(Media $media): void
    {
        if ('' === $this->copyMedia || '0' === $this->copyMedia) {
            return;
        }

        if (! file_exists($media->getPath())) {
            throw new Exception('Media file not found: '.$media->getPath());
        }

        $destination = $this->exportDir.'/'.$this->copyMedia.'/'.$media->getFileName();
        $this->filesystem->copy($media->getPath(), $destination);
    }

    private function getCsvFilePath(): string
    {
        if ('' !== $this->copyMedia && '0' !== $this->copyMedia) {
            return $this->exportDir.'/'.$this->copyMedia.'/'.self::INDEX_FILE;
        }

        return $this->mediaDir.'/'.self::INDEX_FILE;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        mkdir($directory, 0755, true);
    }
}
