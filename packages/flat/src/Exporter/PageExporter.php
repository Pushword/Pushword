<?php

namespace Pushword\Flat\Exporter;

use DateTime;
use DateTimeInterface;
use League\Csv\Writer;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Utils\Entity;
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

    /**
     * @param string[] $pageIndexColumns
     */
    public function __construct(
        private readonly AppPool $apps,
        private readonly PageRepository $pageRepo,
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

            $locale = '' !== $page->getLocale() ? $page->getLocale() : $defaultLocale;

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
            'id' => null !== $page->getId() ? (string) $page->getId() : '',
            'slug' => $page->getSlug(),
            'h1' => '' !== $h1 ? $h1 : $page->getTitle(),
            'publishedAt' => null !== $page->getPublishedAt() ? $page->getPublishedAt()->format('Y-m-d H:i') : '',
            'locale' => $page->getLocale(),
            'parentPage' => null !== $page->getParentPage() ? $page->getParentPage()->getSlug() : '',
            'tags' => trim($page->getTags()),
        ];

        /** @var array<string, mixed> $customProperties */
        $customProperties = $page->getCustomProperties();
        foreach ($customColumns as $column) {
            $row[$column] = array_key_exists($column, $customProperties)
                ? MediaCsvHelper::encodeValue($customProperties[$column])
                : '';
        }

        return $row;
    }

    private function exportPage(Page $page, bool $force = false): void
    {
        $exportFilePath = $this->exportDir.'/'.$page->getSlug().'.md';
        if (
            false === $force
            && file_exists($exportFilePath)
            && filemtime($exportFilePath) >= $page->safegetUpdatedAt()->getTimestamp()
        ) {
            return;
        }

        $properties = array_unique([...['title', 'h1', 'slug', 'id'], ...Entity::getProperties($page)]);

        $data = [];
        foreach ($properties as $property) {
            $value = $this->getValue($property, $page);
            if (null === $value) {
                continue;
            }

            $data[$property] = $value;
        }

        $metaData = Yaml::dump($data, indent: 2);
        $content = '---'.\PHP_EOL.$metaData.'---'.\PHP_EOL.\PHP_EOL.$page->getMainContent();

        $this->filesystem->dumpFile($exportFilePath, $content);
    }

    /**
     * @return scalar|null
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

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (null === $value) {
            return null;
        }

        if (
            in_array($property, ['customProperties', 'tags'], true)
            && in_array($value, [null, [], ''], true)
        ) {
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

        assert(is_scalar($value));

        return $value;
    }
}
