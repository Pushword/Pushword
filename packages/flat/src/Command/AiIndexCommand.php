<?php

namespace Pushword\Flat\Command;

use League\Csv\Writer as CsvWriter;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Flat\FlatFileContentDirFinder;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pw:ai-index',
    description: 'Generate pageIndex.csv and mediaIndex.csv with page and media metadata < Useful for AI tools.'
)]
final class AiIndexCommand
{
    /** @var array<string> */
    private array $mediaList;

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
        private readonly FlatFileContentDirFinder $contentDirFinder,
        private readonly AppPool $apps,
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
        $this->mediaList = array_map(fn (Media $media): string => $media->getMedia(), $this->medias);
        $this->pageSlugList = array_map(fn (Page $page): string => $page->getSlug(), $this->pages);
        $host ??= '';

        $app = $this->apps->switchCurrentApp($host)->get();
        $host = $app->getMainHost();

        $this->exportDir = '' !== $exportDir ? $exportDir
            : ($this->contentDirFinder->has($host)
                ? $this->contentDirFinder->get($host)
                : $this->projectDir.'/var/export/'.uniqid());

        $result = $this->createPageIndex($output, $host);

        $result2 = $this->createMediaIndex($output, $host);

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

    private function createMediaIndex(
        OutputInterface $output,
        string $host,
    ): int {
        $output->writeln('Generating mediaIndex.csv...');

        $output->writeln('Found '.count($this->mediaList).' media files');

        $writer = $this->getCsvWriter($this->exportDir.'/mediaIndex.csv');
        // Write CSV header
        $writer->insertOne([
            'media',
            'mimeType',
            'name',
            'usedInPages',
        ]);

        $rows = [];
        foreach ($this->medias as $media) {
            $usedInPages = $this->mediaUsedInPage[$media->getMedia()] ?? [];

            $rows[] = [
                $media->getMedia(),
                $media->getMimeType() ?? '',
                $media->getName(),
                implode(', ', $usedInPages),
            ];
        }

        $writer->insertAll($rows);

        $output->writeln('File generated.');

        return Command::SUCCESS;
    }

    private function createPageIndex(OutputInterface $output, string $host): int
    {
        $output->writeln('Generating pageIndex.csv...');

        $pages = '' === $host
            ? $this->pageRepository->findAll()
            : $this->pageRepository->findByHost($host);

        $output->writeln('Found '.count($pages).' pages');

        $writer = $this->getCsvWriter($this->exportDir.'/pageIndex.csv');

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
            $mediaUsed = $this->extractMediaUsed($page);
            $pageLinked = $this->extractPageLinked($page);
            $length = strlen($page->getMainContent());

            $rows[] = [
                $page->getSlug(),
                $page->getTitle() ?? $page->getH1(),
                $page->getCreatedAt(false)?->format('Y-m-d H:i:s') ?? '',
                $page->getTags(),
                $page->getSearchExcrept() ?? '',
                implode(', ', $mediaUsed),
                $page->getParentPage()?->getSlug() ?? '',
                implode(', ', $pageLinked),
                $length,
            ];
        }

        $writer->insertAll($rows);

        $output->writeln('File generated.');

        return Command::SUCCESS;
    }

    /** @var array<string, array<string>> */
    private array $mediaUsedInPage = [];

    /**
     * @return string[]
     */
    private function extractMediaUsed(Page $page): array
    {
        $mediaUsed = [];
        $content = $page->getMainContent();

        foreach ($this->mediaList as $media) {
            if (str_contains($content, $media)) {
                $mediaUsed[] = $media;

                if (! isset($this->mediaUsedInPage[$media])) {
                    $this->mediaUsedInPage[$media] = [];
                }

                $this->mediaUsedInPage[$media][] = $page->getSlug();
            }
        }

        return $mediaUsed;
    }

    /**
     * @return string[]
     */
    private function extractPageLinked(Page $page): array
    {
        $pageLinked = [];
        $content = $page->getMainContent();

        foreach ($this->pageSlugList as $pageSlug) {
            if ($pageSlug === $page->getSlug()) {
                continue;
            }

            if (str_contains($content, $pageSlug)) {
                $pageLinked[] = $pageSlug;
            }
        }

        return $pageLinked;
    }
}
