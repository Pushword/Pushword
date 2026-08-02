<?php

namespace Pushword\Flat\Exporter;

use DateTime;
use Exception;
use League\Csv\Writer;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Serializer\PageFileSerializer;
use Pushword\Flat\Sync\SnippetSync;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

final class PageExporter
{
    public const string INDEX_FILE = 'index.csv';

    public const string DRAFT_INDEX_FILE = 'index.draft.csv';

    public string $exportDir = '';

    private readonly Filesystem $filesystem;

    /** @var string[] */
    private readonly array $pageIndexColumns;

    private int $exportedCount = 0;

    private int $skippedCount = 0;

    private ?OutputInterface $output = null;

    /**
     * @param string[] $pageIndexColumns
     */
    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly PageRepository $pageRepo,
        private readonly PageFileSerializer $serializer,
        array $pageIndexColumns = [],
        /** @var string[] Filenames excluded from sync (e.g. CLAUDE.md, README.md) */
        private readonly array $excludeFiles = [],
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->filesystem = new Filesystem();
        $this->pageIndexColumns = [] !== $pageIndexColumns
            ? $pageIndexColumns
            : ['slug', 'h1', 'publishedAt', 'locale', 'parentPage', 'tags'];
    }

    public function setOutput(?OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function exportPages(bool $force = false): void
    {
        $this->exportedCount = 0;
        $this->skippedCount = 0;

        $pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());

        $this->exportPagesArray($pages, $force);
    }

    /**
     * Export only pages matching the given slugs (used after import to regenerate index CSV).
     *
     * @param string[] $slugs
     * @param Page[]   $pages Pre-loaded pages to avoid extra DB query; if empty, loads from DB
     */
    public function exportPagesSubset(array $slugs, array $pages = []): void
    {
        $this->exportedCount = 0;
        $this->skippedCount = 0;

        if ([] === $pages) {
            $pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());
        }

        if ([] === $slugs) {
            $this->exportIndex($pages);

            return;
        }

        $slugSet = array_flip($slugs);
        $subset = array_filter($pages, static fn (Page $page): bool => isset($slugSet[$page->slug]));

        foreach ($subset as $page) {
            if ($page->hasRedirection()) {
                continue;
            }

            $this->exportPageSafe($page);
        }

        $this->exportIndex($pages);
    }

    /**
     * @param Page[] $pages
     */
    private function exportPagesArray(array $pages, bool $force): void
    {
        // Filter out redirections for export
        $exportablePages = array_filter($pages, static fn (Page $page): bool => ! $page->hasRedirection());

        // Delete .md files for pages that have become redirections
        $redirectionPages = array_filter($pages, static fn (Page $page): bool => $page->hasRedirection());
        foreach ($redirectionPages as $page) {
            $mdFilePath = $this->exportDir.'/'.$page->slug.'.md';
            if ($this->filesystem->exists($mdFilePath)) {
                $this->filesystem->remove($mdFilePath);
                $this->output?->writeln(\sprintf('Deleted %s.md (now a redirection)', $page->slug));
            }
        }

        foreach ($exportablePages as $page) {
            $this->exportPageSafe($page, $force);
        }

        // $pages comes from findByHost(): an authoritative, complete list for this
        // host. An empty $exportablePages therefore means the host genuinely has no
        // markdown-backed pages (e.g. its last one was just deleted), so every
        // exported .md is an orphan and cleanup must still run.
        $this->deleteOrphanedFiles($exportablePages, authoritative: true);

        $this->exportIndex($pages);
    }

    /**
     * Remove .md files whose page no longer exists in the database — the mirror
     * of the import side's deleteMissingPages(). Without this, a slug rename
     * leaves the old-slug file behind and a hard delete leaves the file behind;
     * both then resurrect the page on the next `--mode=import`.
     *
     * @param Page[] $exportablePages Non-redirection pages for the current host
     * @param bool   $authoritative   Whether $exportablePages is the complete host page
     *                                list (from findByHost), so an empty list may be swept
     */
    private function deleteOrphanedFiles(array $exportablePages, bool $authoritative = false): void
    {
        // Safety: without an authoritative list, an empty array is ambiguous (query
        // glitch, wrong host) — never mass-delete when there is nothing to compare against.
        if ([] === $exportablePages && ! $authoritative) {
            return;
        }

        $expectedSlugs = [];
        foreach ($exportablePages as $page) {
            $expectedSlugs[$page->slug] = true;
        }

        foreach ($this->collectExportedMarkdownFiles($this->exportDir) as $filePath) {
            if (isset($expectedSlugs[$this->fileToSlug($filePath)])) {
                continue;
            }

            $this->filesystem->remove($filePath);
            $this->output?->writeln(\sprintf('Deleted %s (no matching page)', basename($filePath)));
        }
    }

    /**
     * Recursively collect page .md files under a content dir, skipping sibling
     * entity dirs (snippets), pending writes, conflict backups and editor
     * backups — matching what the import walker considers a page file.
     *
     * @return string[]
     */
    private function collectExportedMarkdownFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];

        /** @var string[] $entries */
        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if (\in_array($entry, ['.', '..', SnippetSync::DIR], true)) {
                continue;
            }

            // Excluded files (CLAUDE.md, README.md, …) are not page files.
            if (\in_array($entry, $this->excludeFiles, true)) {
                continue;
            }

            $path = $dir.'/'.$entry;

            if (is_dir($path)) {
                $files = [...$files, ...$this->collectExportedMarkdownFiles($path)];

                continue;
            }

            if (str_contains($entry, '~conflict-')) {
                continue;
            }

            if (str_ends_with($entry, '~')) {
                continue;
            }

            if (str_ends_with($entry, '.md') && ! str_ends_with($entry, '.pending.md')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Derive a page slug from an exported file path, mirroring
     * PageImporter::filePathToSlug (including the index ↔ homepage equivalence)
     * so renamed/index files are matched against DB slugs consistently.
     */
    private function fileToSlug(string $filePath): string
    {
        $slug = preg_replace('/\.md$/i', '', str_replace($this->exportDir.'/', '', $filePath)) ?? '';

        if ('index' === $slug) {
            $slug = 'homepage';
        } elseif ('index' === basename($slug)) {
            $slug = substr($slug, 0, -\strlen('index'));
        }

        return Page::normalizeSlug($slug);
    }

    /**
     * Export a single page, isolating failures so one bad page cannot abort the
     * whole export (mirroring the per-file resilience of the import side). A
     * throwing converter, serializer, or lazy-load is logged and skipped.
     */
    private function exportPageSafe(Page $page, bool $force = false): void
    {
        try {
            $exported = $this->exportPage($page, $force);
        } catch (Throwable $throwable) {
            $this->logger?->error('Flat export failed for page {slug}: {message}', [
                'slug' => $page->slug,
                'message' => $throwable->getMessage(),
            ]);

            return;
        }

        if ($exported) {
            $this->output?->writeln(\sprintf('Exported %s.md', $page->slug));
        }
    }

    /**
     * @param Page[] $pages
     */
    private function exportIndex(array $pages): void
    {
        if ([] === $pages) {
            return;
        }

        $defaultLocale = $this->apps->get()->locale;

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

        // Export draft pages to index.draft.csv
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
        $publishedAt = $page->publishedAt;

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
            $row = $this->buildCsvRow($page);

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
        $existingContent = $this->filesystem->exists($csvFilePath) ? $this->filesystem->readFile($csvFilePath) : '';
        if ($newContent === $existingContent) {
            return;
        }

        $this->filesystem->dumpFile($csvFilePath, $newContent);
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

        return
          '# For information only — Auto-generated by Pushword, DO NOT EDIT (changes will be overwritten)'.\PHP_EOL
          .$writer->toString();
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
            $customProperties = $page->customProperties;
            foreach (array_keys($customProperties) as $property) {
                $columns[$property] = true;
            }
        }

        $columns = array_keys($columns);
        sort($columns);

        return $columns;
    }

    /**
     * @return array<string, string|null>
     */
    private function buildCsvRow(Page $page): array
    {
        $h1 = $page->h1;

        return [
            'slug' => $page->slug,
            'h1' => '' !== $h1 ? $h1 : $page->title,
            'publishedAt' => null !== $page->publishedAt ? $page->publishedAt->format('Y-m-d H:i') : '',
            'locale' => $page->locale,
            'parentPage' => null !== $page->parentPage ? $page->parentPage->slug : '',
            'tags' => trim($page->getTags()),
        ];
    }

    public function generatePageContent(Page $page): string
    {
        return $this->serializer->serialize($page);
    }

    private function exportPage(Page $page, bool $force = false): bool
    {
        $exportFilePath = $this->exportDir.'/'.$page->slug.'.md';

        // Fast path: skip if file is newer than DB and not forced (avoids expensive content generation)
        if (
            false === $force
            && $this->filesystem->exists($exportFilePath)
            && filemtime($exportFilePath) >= $page->updatedAt->getTimestamp() // @phpstan-ignore method.nonObject
        ) {
            ++$this->skippedCount;

            return false;
        }

        $newContent = $this->generatePageContent($page);

        // Skip if content unchanged (smart update to avoid unnecessary file writes)
        if ($this->filesystem->exists($exportFilePath)) {
            $existingContent = $this->filesystem->readFile($exportFilePath);
            if ($newContent === $existingContent) {
                ++$this->skippedCount;

                return false;
            }
        }

        ++$this->exportedCount;
        $this->filesystem->dumpFile($exportFilePath, $newContent);

        // Sync file timestamp with page updatedAt to prevent import/export cycles
        $this->filesystem->touch($exportFilePath, $page->updatedAt->getTimestamp()); // @phpstan-ignore method.nonObject

        return true;
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
