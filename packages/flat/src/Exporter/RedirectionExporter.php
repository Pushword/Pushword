<?php

declare(strict_types=1);

namespace Pushword\Flat\Exporter;

use League\Csv\Writer;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;

final class RedirectionExporter
{
    public const string INDEX_FILE = 'redirection.csv';

    public const array BASE_COLUMNS = ['slug', 'target', 'code'];

    public string $exportDir = '';

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly PageRepository $pageRepo,
    ) {
    }

    /**
     * Whether `redirection.csv` already reflects the host's redirection pages.
     *
     * Unlike the page export, {@see self::exportRedirections()} rewrites the file
     * every time it runs, so its mtime is when it was last written — and a
     * redirection changed since then is exactly a redirection newer than the file.
     * A host with no redirection writes nothing, so nothing can be out of date.
     *
     * Strictly newer, where the page side can accept equality: there an mtime is
     * *set* from updatedAt, so equal means mirrored, while here equal only means
     * "written in the same second as the edit", which proves nothing at mtime's
     * one-second resolution.
     */
    public function isMirrorCurrent(string $dir): bool
    {
        $latest = $this->pageRepo->findLatestRedirectionUpdate($this->apps->get()->getMainHost());

        if (0 === $latest) {
            return true;
        }

        $csvFilePath = $dir.'/'.self::INDEX_FILE;

        return is_file($csvFilePath) && (int) filemtime($csvFilePath) > $latest;
    }

    public function exportRedirections(): void
    {
        $pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());

        $redirections = array_filter($pages, static fn (Page $page): bool => $page->hasRedirection());

        if ([] === $redirections) {
            return;
        }

        $header = self::BASE_COLUMNS;

        /** @var array<int, array<string, string|null>> $rows */
        $rows = [];
        foreach ($redirections as $page) {
            $rows[] = $this->buildRow($page);
        }

        $csvFilePath = $this->exportDir.'/'.self::INDEX_FILE;

        $writer = Writer::from($csvFilePath, 'w+');
        $writer->insertOne($header);
        $writer->insertAll($rows);
    }

    /**
     * @return array<string, string|null>
     */
    private function buildRow(Page $page): array
    {
        return [
            'slug' => $page->slug,
            'target' => $page->getRedirectionUrl(),
            'code' => (string) $page->getRedirectionCode(),
        ];
    }
}
