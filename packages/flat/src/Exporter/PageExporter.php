<?php

namespace Pushword\Flat\Exporter;

use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use League\Csv\Writer;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Utils\Entity;
use Pushword\Flat\Converter\PropertyConverterRegistry;
use Pushword\Flat\Converter\PublishedAtConverter;
use Stringable;
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

    public function exportPages(bool $force = false): void
    {
        $this->exportedCount = 0;
        $this->skippedCount = 0;

        $pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());

        foreach ($pages as $page) {
            if ($page->hasRedirection()) {
                continue; // Redirections go to redirection.csv, not .md files
            }

            $this->exportPage($page, $force);
        }

        $this->exportIndex($pages);
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
    private function exportIndex(array $pages): void
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
            $this->exportIndexForLocale($localePages, $filename);
        }

        // Export draft pages to iDraft.csv
        foreach ($draftsByLocale as $locale => $localePages) {
            $filename = $this->getIndexFilename($locale, $defaultLocale, self::DRAFT_INDEX_FILE);
            $this->exportIndexForLocale($localePages, $filename);
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
    private function exportIndexForLocale(array $pages, string $filename): void
    {
        $customColumns = $this->collectCustomColumns($pages);
        $header = array_merge($this->pageIndexColumns, $customColumns);

        /** @var array<int, array<string, string|null>> $rows */
        $rows = [];
        foreach ($pages as $page) {
            $row = $this->buildCsvRow($page, $customColumns);

            $orderedRow = [];
            foreach ($header as $column) {
                $orderedRow[$column] = $row[$column] ?? null;
            }

            $rows[] = $orderedRow;
        }

        $csvFilePath = $this->exportDir.'/'.$filename;

        $writer = Writer::from($csvFilePath, 'w+');
        $writer->insertOne($header);
        $writer->insertAll($rows);
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
    private function buildCsvRow(Page $page, array $customColumns): array
    {
        $h1 = $page->getH1();

        $row = [
            'id' => null !== $page->id ? (string) $page->id : '',
            'slug' => $page->getSlug(),
            'h1' => '' !== $h1 ? $h1 : $page->getTitle(),
            'publishedAt' => null !== $page->getPublishedAt() ? $page->getPublishedAt()->format('Y-m-d H:i') : '',
            'locale' => $page->locale,
            'parentPage' => null !== $page->getParentPage() ? $page->getParentPage()->getSlug() : '',
            'tags' => trim($page->getTags()),
        ];

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

    private function exportPage(Page $page, bool $force = false): void
    {
        $exportFilePath = $this->exportDir.'/'.$page->getSlug().'.md';
        if (
            false === $force
            && file_exists($exportFilePath)
            && filemtime($exportFilePath) >= $page->updatedAt->getTimestamp() // @phpstan-ignore method.nonObject
        ) {
            ++$this->skippedCount;

            return;
        }

        ++$this->exportedCount;

        $properties = array_unique([...['title', 'h1', 'slug', 'id'], ...Entity::getProperties($page)]);

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
        $content = '---'.\PHP_EOL.$metaData.'---'.\PHP_EOL.\PHP_EOL.$page->getMainContent();

        $this->filesystem->dumpFile($exportFilePath, $content);

        // Sync file timestamp with page updatedAt to prevent import/export cycles
        touch($exportFilePath, $page->updatedAt->getTimestamp()); // @phpstan-ignore method.nonObject
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
