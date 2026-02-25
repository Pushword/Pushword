<?php

namespace Pushword\Flat\Importer;

use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Exporter\RedirectionExporter;

/**
 * Import redirections from redirection.csv file.
 */
final class RedirectionImporter
{
    /** @var array<string, array<string, string|null>> Indexed by slug */
    private array $indexData = [];

    /** @var string[] Slugs of redirections found in CSV (for deletion detection) */
    private array $importedSlugs = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteRegistry $apps,
        private readonly PageRepository $pageRepo,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function reset(): void
    {
        $this->indexData = [];
        $this->importedSlugs = [];
    }

    public function loadIndex(string $dir): void
    {
        $indexPath = $dir.'/'.RedirectionExporter::INDEX_FILE;
        $reader = $this->createReader($indexPath);

        if (null === $reader) {
            return;
        }

        $header = $this->prepareHeader($reader);
        if ([] === $header) {
            return;
        }

        $records = $reader->getRecords();
        foreach ($records as $row) {
            $slug = $row['slug'] ?? '';
            if ('' !== $slug) {
                $this->indexData[$slug] = $row;
                $this->importedSlugs[] = $slug;
            }
        }
    }

    public function hasIndexData(): bool
    {
        return [] !== $this->indexData;
    }

    /**
     * @return string[]
     */
    public function getImportedSlugs(): array
    {
        return $this->importedSlugs;
    }

    public function importAll(): void
    {
        $host = $this->apps->get()->getMainHost();

        foreach ($this->indexData as $slug => $row) {
            $this->importRedirection($slug, $row, $host);
        }

        $this->em->flush();
    }

    /**
     * @param array<string, string|null> $row
     */
    private function importRedirection(string $slug, array $row, string $host): void
    {
        $target = $row['target'] ?? '';
        $code = $row['code'] ?? '301';

        if ('' === $target) {
            $this->logger?->warning('Skipping redirection with empty target: {slug}', ['slug' => $slug]);

            return;
        }

        // Find by slug and host
        $page = $this->pageRepo->findOneBy(['slug' => $slug, 'host' => $host]);

        // Create new if not found
        if (null === $page) {
            $page = new Page();
            $page->setSlug($slug);
            $page->host = $host;
            $this->em->persist($page);
        }

        // Auto-detect locale from slug prefix
        $locale = $this->detectLocaleFromSlug($slug);
        $page->locale = $locale;

        // Set redirection content
        $mainContent = 'Location: '.$target;
        if ('301' !== $code && '' !== $code) {
            $mainContent .= ' '.$code;
        }

        $page->setMainContent($mainContent);

        // Set a minimal h1 if not set
        if ('' === $page->getH1()) {
            $page->setH1('Redirection');
        }
    }

    private function detectLocaleFromSlug(string $slug): string
    {
        $locales = $this->apps->get()->getLocales();
        foreach ($locales as $locale) {
            if (str_starts_with($slug, $locale.'/')) {
                return $locale;
            }
        }

        return $this->apps->get()->getLocale();
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

        return array_values(array_filter($header, static fn (string $col): bool => '' !== trim($col)));
    }
}
