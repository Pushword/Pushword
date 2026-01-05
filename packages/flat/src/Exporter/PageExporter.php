<?php

namespace Pushword\Flat\Exporter;

use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Exception;
use League\Csv\Writer;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Utils\Entity;
use Pushword\Flat\Converter\PropertyConverterRegistry;
use Pushword\Flat\Converter\PublishedAtConverter;
use Stringable;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final class PageExporter
{
    public const string INDEX_FILE = 'index.csv';

    public const string DRAFT_INDEX_FILE = 'iDraft.csv';

    public string $exportDir = '';

    private readonly Filesystem $filesystem;

    private readonly ExporterDefaultValueHelper $defaultValue;

    /** @var string[] */
    private readonly array $pageIndexColumns;

    private int $exportedCount = 0;

    private int $skippedCount = 0;

    private ?OutputInterface $output = null;

    /**
     * @param string[] $pageIndexColumns
     */
    public function __construct(
        private readonly AppPool $apps,
        private readonly PageRepository $pageRepo,
        private readonly PropertyConverterRegistry $converterRegistry,
        array $pageIndexColumns = [],
    ) {
        $this->filesystem = new Filesystem();
        $this->defaultValue = new ExporterDefaultValueHelper();
        $this->pageIndexColumns = [] !== $pageIndexColumns
            ? $pageIndexColumns
            : ['id', 'slug', 'h1', 'publishedAt', 'locale', 'parentPage', 'tags'];
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function exportPages(bool $force = false, bool $skipId = false): void
    {
        $this->exportedCount = 0;
        $this->skippedCount = 0;

        $pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());

        // Filter out redirections for export
        $exportablePages = array_filter($pages, static fn (Page $page): bool => ! $page->hasRedirection());

        // Delete .md files for pages that have become redirections
        $redirectionPages = array_filter($pages, static fn (Page $page): bool => $page->hasRedirection());
        foreach ($redirectionPages as $page) {
            $mdFilePath = $this->exportDir.'/'.$page->getSlug().'.md';
            if (file_exists($mdFilePath)) {
                $this->filesystem->remove($mdFilePath);
                $this->output?->writeln(\sprintf('Deleted %s.md (now a redirection)', $page->getSlug()));
            }
        }

        foreach ($exportablePages as $page) {
            $exported = $this->exportPage($page, $force, $skipId);

            if ($exported) {
                $this->output?->writeln(\sprintf('Exported %s.md', $page->getSlug()));
            }
        }

        $this->exportIndex($pages, $skipId);
    }

    /**
     * Export only index.csv files without re-exporting .md files.
     * Useful after import to ensure indexes reflect current database state.
     */
    public function exportIndexOnly(): void
    {
        $pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());
        $this->exportIndex($pages);
    }

    /**
     * @param Page[] $pages
     */
    private function exportIndex(array $pages, bool $skipId = false): void
    {
        if ([] === $pages) {
            return;
        }

        $defaultLocale = $this->apps->get()->getLocale();

        /** @var array<string, Page[]> $publishedByLocale */
        $publishedByLocale = [];
        /** @var array<string, Page[]> $draftsByLocale */
        $draftsByLocale = [];

        foreach ($pages as $page) {
            if ($page->hasRedirection()) {
                continue; // Redirections go to redirection.csv
            }

            $locale = '' !== $page->locale ? $page->locale : $defaultLocale;

            if ($this->isPublished($page)) {
                $publishedByLocale[$locale][] = $page;
            } else {
                $draftsByLocale[$locale][] = $page;
            }
        }

        // Export published pages to index.csv
        foreach ($publishedByLocale as $locale => $localePages) {
            $filename = $this->getIndexFilename($locale, $defaultLocale, self::INDEX_FILE);
            $this->exportIndexForLocale($localePages, $filename, $skipId);
        }

        // Export draft pages to iDraft.csv
        foreach ($draftsByLocale as $locale => $localePages) {
            $filename = $this->getIndexFilename($locale, $defaultLocale, self::DRAFT_INDEX_FILE);
            $this->exportIndexForLocale($localePages, $filename, $skipId);
        }
    }

    private function getIndexFilename(string $locale, string $defaultLocale, string $baseFilename): string
    {
        if ($locale === $defaultLocale) {
            return $baseFilename;
        }

        if (is_dir($this->exportDir.'/'.$locale)) {
            return $locale.'/'.$baseFilename;
        }

        $extension = pathinfo($baseFilename, \PATHINFO_EXTENSION);
        $name = pathinfo($baseFilename, \PATHINFO_FILENAME);

        return $name.'.'.$locale.'.'.$extension;
    }

    private function isPublished(Page $page): bool
    {
        $publishedAt = $page->getPublishedAt();

        return null !== $publishedAt && $publishedAt <= new DateTime();
    }

    /**
     * @param Page[] $pages
     */
    private function exportIndexForLocale(array $pages, string $filename, bool $skipId = false): void
    {
        $customColumns = $this->collectCustomColumns($pages);
        $indexColumns = $skipId ? array_filter($this->pageIndexColumns, static fn ($col): bool => 'id' !== $col) : $this->pageIndexColumns;
        $header = array_merge($indexColumns, $customColumns);

        /** @var array<int, array<string, string|null>> $rows */
        $rows = [];
        foreach ($pages as $page) {
            $row = $this->buildCsvRow($page, $customColumns, $skipId);

            $orderedRow = [];
            foreach ($header as $column) {
                $orderedRow[$column] = $row[$column] ?? null;
            }

            $rows[] = $orderedRow;
        }

        $csvFilePath = $this->exportDir.'/'.$filename;

        // Generate new CSV content to compare
        $newContent = $this->generateCsvContent($header, $rows);

        // Compare with existing content - skip if unchanged
        $existingContent = file_exists($csvFilePath) ? file_get_contents($csvFilePath) : '';
        if ($newContent === $existingContent) {
            return;
        }

        file_put_contents($csvFilePath, $newContent);
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
     * @param Page[] $pages
     *
     * @return string[]
     */
    private function collectCustomColumns(array $pages): array
    {
        $columns = [];
        foreach ($pages as $page) {
            /** @var array<string, mixed> $customProperties */
            $customProperties = $page->getCustomProperties();
            foreach (array_keys($customProperties) as $property) {
                $columns[$property] = true;
            }
        }

        $columns = array_keys($columns);
        sort($columns);

        return $columns;
    }

    /**
     * @param string[] $customColumns
     *
     * @return array<string, string|null>
     */
    private function buildCsvRow(Page $page, array $customColumns, bool $skipId = false): array
    {
        $h1 = $page->getH1();

        $row = [
            'slug' => $page->getSlug(),
            'h1' => '' !== $h1 ? $h1 : $page->getTitle(),
            'publishedAt' => null !== $page->getPublishedAt() ? $page->getPublishedAt()->format('Y-m-d H:i') : '',
            'locale' => $page->locale,
            'parentPage' => null !== $page->getParentPage() ? $page->getParentPage()->getSlug() : '',
            'tags' => trim($page->getTags()),
        ];

        if (! $skipId) {
            return ['id' => null !== $page->id ? (string) $page->id : ''] + $row;
        }

        // custom properties
        // /** @var array<string, mixed> $customProperties */
        // $customProperties = $page->getCustomProperties();
        // foreach ($customColumns as $column) {
        //     $row[$column] = array_key_exists($column, $customProperties)
        //         ? MediaCsvHelper::encodeValue($customProperties[$column])
        //         : '';
        // }

        return $row;
    }

    private function exportPage(Page $page, bool $force = false, bool $skipId = false): bool
    {
        $exportFilePath = $this->exportDir.'/'.$page->getSlug().'.md';

        // Build content first to enable content comparison
        // When skipId is true, we don't force 'id' into base properties, but we preserve it if it already exists in the file
        $baseProperties = $skipId ? ['title', 'h1', 'slug'] : ['title', 'h1', 'slug', 'id'];
        $entityProperties = Entity::getProperties($page);

        // If skipId, check if the existing file already has an ID - if so, keep 'id' in properties
        if ($skipId && file_exists($exportFilePath)) {
            $existingContent = file_get_contents($exportFilePath);
            if (false !== $existingContent && 1 === preg_match('/^id:\s*\d+/m', $existingContent)) {
                $baseProperties[] = 'id';
            } else {
                $entityProperties = array_filter($entityProperties, static fn (string $prop): bool => 'id' !== $prop);
            }
        } elseif ($skipId) {
            // New file with skipId - don't add id
            $entityProperties = array_filter($entityProperties, static fn (string $prop): bool => 'id' !== $prop);
        }

        $properties = array_unique([...$baseProperties, ...$entityProperties]);

        $data = [];
        foreach ($properties as $property) {
            if ('customProperties' === $property) {
                continue; // Will be unpacked separately
            }

            $value = $this->getValue($property, $page);
            if (null === $value) {
                continue;
            }

            $data[$property] = $value;
        }

        // Unpack custom properties at top level and apply converters
        foreach ($page->getCustomProperties() as $key => $value) {
            $data[$key] = $this->converterRegistry->toFlatValue($key, $value);
        }

        $metaData = Yaml::dump($data, indent: 2);
        $newContent = '---'.\PHP_EOL.$metaData.'---'.\PHP_EOL.\PHP_EOL.$page->getMainContent();

        // Skip if content unchanged (smart update to avoid unnecessary file writes)
        if (file_exists($exportFilePath)) {
            $existingContent = file_get_contents($exportFilePath);
            if ($newContent === $existingContent) {
                ++$this->skippedCount;

                return false;
            }
        }

        // Skip if file is newer and not forced (timestamp-based skip)
        if (
            false === $force
            && file_exists($exportFilePath)
            && filemtime($exportFilePath) >= $page->updatedAt->getTimestamp() // @phpstan-ignore method.nonObject
        ) {
            ++$this->skippedCount;

            return false;
        }

        ++$this->exportedCount;
        $this->filesystem->dumpFile($exportFilePath, $newContent);

        // Sync file timestamp with page updatedAt to prevent import/export cycles
        touch($exportFilePath, $page->updatedAt->getTimestamp()); // @phpstan-ignore method.nonObject

        return true;
    }

    /**
     * @return scalar|string[]|null
     */
    private function getValue(string $property, Page $page): mixed
    {
        if ('mainContent' === $property) {
            return null;
        }

        $getter = 'get'.ucfirst($property);
        $value = $page->$getter(); // @phpstan-ignore-line

        if ('publishedAt' === $property) {
            assert(null === $value || $value instanceof DateTimeInterface);

            return PublishedAtConverter::toFlatValue($value);
        }

        if ($value instanceof Page) {
            $value = $value->getSlug();
        }

        if ($value instanceof Collection) {
            if ($value->isEmpty()) {
                return null;
            }

            $slugs = [];
            foreach ($value as $item) {
                if ($item instanceof Page) {
                    $slugs[] = $item->getSlug();
                }
            }

            return [] === $slugs ? null : $slugs;
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (null === $value) {
            return null;
        }

        if ('tags' === $property && in_array($value, [null, [], ''], true)) {
            return null;
        }

        if ($value === $this->defaultValue->get($property)) {
            return null;
        }

        if (in_array($property, ['createdAt', 'updatedAt', 'host', 'slug'], true)) {
            return null;
        }

        if ('locale' === $property && $value === $this->apps->get()->getLocale()) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i');
        }

        if (! is_scalar($value)) {
            return null;
        }

        return $value;
    }

    public function getExportedCount(): int
    {
        return $this->exportedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }
}
