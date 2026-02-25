<?php

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
            'slug' => $page->getSlug(),
            'target' => $page->getRedirectionUrl(),
            'code' => (string) $page->getRedirectionCode(),
        ];
    }
}
