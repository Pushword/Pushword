<?php

namespace Pushword\Flat\Command;

use League\Csv\Writer as CsvWriter;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\MediaUsageRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pw:ai-index',
    description: 'Generate pages.csv and medias.csv with page and media metadata < Useful for AI tools.'
)]
final class AiIndexCommand
{
    /** @var array<string> */
    private array $pageSlugList;

    /** @var array<Page> */
    private array $pages;

    /** @var array<Media> */
    private array $medias;

    private string $exportDir;

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly MediaUsageRepository $mediaUsageRepository,
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly SiteRegistry $apps,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Argument(name: 'exportDir')]
        string $exportDir = '',
    ): int {
        $this->pages = $this->pageRepository->findAll();
        $this->medias = $this->mediaRepository->findAll();
        $this->pageSlugList = array_map(static fn (Page $page): string => $page->slug, $this->pages);
        $host ??= '';

        $app = $this->apps->switchSite($host)->get();
        $host = $app->getMainHost();

        $this->exportDir = '' !== $exportDir ? $exportDir
            : ($this->contentDirFinder->has($host)
                ? $this->contentDirFinder->get($host)
                : $this->projectDir.'/var/export/'.uniqid());

        $exportedPages = '' === $host
            ? $this->pages
            : $this->pageRepository->findByHost($host);

        $this->loadMediaUsage($exportedPages);

        $result = $this->createpages($output, $exportedPages);

        $result2 = $this->createmedias($output);

        $output->writeln(\sprintf('<comment>:: peak memory: %.1f MB</comment>', memory_get_peak_usage(true) / 1024 / 1024));

        if (Command::SUCCESS === $result && Command::SUCCESS === $result2) {
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    private function getCsvWriter(string $outputFile): CsvWriter
    {
        $writer = CsvWriter::from($outputFile, 'w+');
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');
        $writer->setEscape('\\');

        return $writer;
    }

    private function createmedias(
        OutputInterface $output,
    ): int {
        $output->writeln('Generating medias.csv...');

        $output->writeln('Found '.count($this->medias).' media files');

        $writer = $this->getCsvWriter($this->exportDir.'/medias.csv');
        // Write CSV header
        $writer->insertOne([
            'media',
            'mimeType',
            'name',
            'usedInPages',
        ]);

        $rows = [];
        foreach ($this->medias as $media) {
            $usedInPages = $this->mediaUsedInPage[$media->getFileName()] ?? [];

            $rows[] = [
                $media->getFileName(),
                $media->getMimeType() ?? '',
                $media->getAlt(),
                implode(', ', $usedInPages),
            ];
        }

        $writer->insertAll($rows);

        $output->writeln('File generated.');

        return Command::SUCCESS;
    }

    /** @param Page[] $pages */
    private function createpages(OutputInterface $output, array $pages): int
    {
        $output->writeln('Generating pages.csv...');

        $output->writeln('Found '.count($pages).' pages');

        $writer = $this->getCsvWriter($this->exportDir.'/pages.csv');

        // Write CSV header
        $writer->insertOne([
            'slug',
            'title',
            'createdAt',
            'tags',
            'summary',
            'mediaUsed',
            'parentPage',
            'pageLinked',
            'length',
        ]);

        $rows = [];
        foreach ($pages as $page) {
            $mediaUsed = $this->mediaUsedByPage[(int) $page->id] ?? [];
            $pageLinked = $this->extractPageLinked($page);
            $length = strlen($page->mainContent);

            $rows[] = [
                $page->slug,
                '' !== $page->title ? $page->title : $page->h1,
                $page->getCreatedAtNullable()?->format('Y-m-d H:i:s') ?? '',
                $page->getTags(),
                $page->getSearchExcerpt() ?? '',
                implode(', ', $mediaUsed),
                $page->parentPage->slug ?? '',
                implode(', ', $pageLinked),
                $length,
            ];
        }

        $writer->insertAll($rows);

        $output->writeln('File generated.');

        return Command::SUCCESS;
    }

    /** @var array<string, array<string>> media filename => slugs of the pages using it */
    private array $mediaUsedInPage = [];

    /** @var array<int, array<string>> page id => filenames of the media it uses */
    private array $mediaUsedByPage = [];

    /**
     * Both directions of the usage relation, read from `media_usage` in one query
     * and resolved against the ids already in memory.
     *
     * This used to be a `str_contains()` of every media in every page — the reason
     * the relation is stored now. A media the table does not know about exports as
     * unused here: `pw:media:usage:rebuild` is what fills it in.
     *
     * Scoped to the pages being exported, so a per-host export does not credit a
     * media to a page living on another site.
     *
     * @param Page[] $exportedPages
     */
    private function loadMediaUsage(array $exportedPages): void
    {
        $fileNameById = [];
        foreach ($this->medias as $media) {
            $fileNameById[(int) $media->id] = $media->getFileName();
        }

        $slugById = [];
        foreach ($exportedPages as $page) {
            $slugById[(int) $page->id] = $page->slug;
        }

        foreach ($this->mediaUsageRepository->findAllEdges() as $edge) {
            $fileName = $fileNameById[$edge['mediaId']] ?? null;
            $slug = $slugById[$edge['pageId']] ?? null;
            if (null === $fileName) {
                continue;
            }

            if (null === $slug) {
                continue;
            }

            $this->mediaUsedInPage[$fileName][] = $slug;
            $this->mediaUsedByPage[$edge['pageId']][] = $fileName;
        }
    }

    /**
     * @return string[]
     */
    private function extractPageLinked(Page $page): array
    {
        $pageLinked = [];
        $content = $page->mainContent;

        foreach ($this->pageSlugList as $pageSlug) {
            if ($pageSlug === $page->slug) {
                continue;
            }

            if (str_contains($content, $pageSlug)) {
                $pageLinked[] = $pageSlug;
            }
        }

        return $pageLinked;
    }
}
