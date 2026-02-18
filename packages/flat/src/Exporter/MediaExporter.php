<?php

namespace Pushword\Flat\Exporter;

use Exception;
use League\Csv\Writer;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaStorageAdapter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

final class MediaExporter
{
    public const string CSV_FILE = 'media.csv';

    public string $copyMedia = '';

    public string $exportDir = '';

    public string $csvDir = '';

    private int $exportedCount = 0;

    private ?OutputInterface $output = null;

    public function __construct(
        private readonly MediaRepository $mediaRepo,
        private readonly MediaStorageAdapter $mediaStorage,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function exportMedias(): void
    {
        $this->exportedCount = 0;

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

        // Generate new CSV content
        $newContent = $this->generateCsvContent($header, $rows);

        $csvFilePath = $this->isExportMode()
            ? $this->exportDir.'/'.$this->copyMedia.'/'.self::CSV_FILE
            : $this->csvDir.'/'.self::CSV_FILE;

        $this->filesystem->mkdir(\dirname($csvFilePath));
        $existingContent = $this->filesystem->exists($csvFilePath) ? $this->filesystem->readFile($csvFilePath) : '';
        if ($newContent === $existingContent) {
            return;
        }

        $this->filesystem->dumpFile($csvFilePath, $newContent);

        $this->exportedCount = \count($medias);
        $this->output?->writeln(\sprintf('Exported %d media to media.csv', $this->exportedCount));
    }

    /**
     * @param string[]                               $header
     * @param array<int, array<string, string|null>> $rows
     */
    private function generateCsvContent(array $header, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if (false === $stream) {
            throw new Exception('Failed to open temp stream');
        }

        $writer = Writer::from($stream);
        $writer->insertOne($header);
        $writer->insertAll($rows);

        return $writer->toString();
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
            'id' => null !== $media->id ? (string) $media->id : '',
            'fileName' => $media->getFileName(),
            'alt' => $media->getAlt(true),
            'tags' => trim($media->getTags()),
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

        if (! $this->mediaStorage->fileExists($media->getFileName())) {
            throw new Exception('Media file not found: '.$media->getFileName());
        }

        $destination = $this->exportDir.'/'.$this->copyMedia.'/'.$media->getFileName();
        $this->filesystem->mkdir(\dirname($destination));

        // Read from storage and write to local export directory
        $stream = $this->mediaStorage->readStream($media->getFileName());
        $this->filesystem->dumpFile($destination, $stream);
        fclose($stream);
    }

    private function isExportMode(): bool
    {
        return '' !== $this->copyMedia && '0' !== $this->copyMedia;
    }

    public function getExportedCount(): int
    {
        return $this->exportedCount;
    }
}
