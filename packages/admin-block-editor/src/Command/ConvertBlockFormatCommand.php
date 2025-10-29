<?php

namespace Pushword\AdminBlockEditor\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\AdminBlockEditor\EditorJsHelper;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'pushword:block:upgrade', description: 'Convert editorjs blocks (image, gallery, attaches) to new format.')]
final readonly class ConvertBlockFormatCommand
{
    public function __construct(
        private PageRepository $pageRepo,
        private EntityManagerInterface $em
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $pages = $this->pageRepo->findAll();
        $io->progressStart(\count($pages));
        foreach ($pages as $page) {
            $this->upgradeBlocks($page);
            $io->progressAdvance();
        }

        $this->em->flush();
        $io->progressFinish();
        $io->success('Done.');

        return Command::SUCCESS;
    }

    private function upgradeBlocks(Page $page): void
    {
        $mainContent = $page->getMainContent();
        $editorJs = EditorJsHelper::tryToDecode($mainContent);
        if (false === $editorJs) {
            return;
        }

        foreach ($editorJs->blocks as $block) {
            if (! property_exists($block, 'type')) {
                continue;
            }

            if ('image' === $block->type) {
                $this->convertImage($block);
            } elseif ('gallery' === $block->type) {
                $this->convertGallery($block);
            } elseif ('attaches' === $block->type) {
                $this->convertAttaches($block);
            }
        }

        $page->setMainContent(json_encode($editorJs, \JSON_THROW_ON_ERROR));
    }

    private function convertImage(object $block): void
    {
        if (! property_exists($block, 'data') || ! \is_object($block->data)) {
            return;
        }

        if (! property_exists($block->data, 'file') || ! \is_object($block->data->file) || ! property_exists($block->data->file, 'media')) {
            return;
        }

        $newData = new stdClass();
        $newData->media = $block->data->file->media;
        $newData->caption = (property_exists($block->data, 'caption') ? $block->data->caption : null) ?? (property_exists($block->data->file, 'name') ? $block->data->file->name : null) ?? '';

        $block->data = $newData;
    }

    /**
     * from
     * "data": [
     *   {
     *     "file": {
     *       "media": "1.jpg",
     *       ...
     *     },
     *     "url": "/media/default/1.jpg",
     *     "caption": "Demo 1"
     *   },
     *   {
     *     "file": {
     *       "media": "2.jpg",
     *       ...
     *     }
     *   }
     * ]
     * to
     * "data": [
     *   "1.jpg",
     *   "2.jpg"
     * ].
     */
    private function convertGallery(object $block): void
    {
        if (! property_exists($block, 'data') || ! \is_array($block->data)) {
            return;
        }

        $mediaNames = [];
        foreach ($block->data as $file) {
            if (\is_object($file) && property_exists($file, 'file') && \is_object($file->file) && property_exists($file->file, 'media')) {
                $mediaNames[] = $file->file->media;
            }
        }

        if ([] === $mediaNames) {
            return;
        }

        $block->data = $mediaNames;
    }

    /**
     * Convert attaches block from complex format to simplified format
     * from
     * "data": {
     *   "file": {
     *     "url": "https://example.com/file.pdf",
     *     "size": "1024",
     *     "name": "document.pdf",
     *     "title": "Document Title",
     *     "extension": "pdf",
     *     "mimeType": "application/pdf",
     *     "customProperties": [],
     *     "createdAt": { ... },
     *     "updatedAt": { ... }
     *   },
     *   "title": "Document Title"
     * }
     * to
     * "data": {
     *   "title": "Document Title",
     *   "file": {
     *     "url": "https://example.com/file.pdf",
     *     "name": "document.pdf",
     *     "size": "1024"
     *   }
     * }.
     */
    private function convertAttaches(object $block): void
    {
        if (! property_exists($block, 'data') || ! \is_object($block->data)) {
            return;
        }

        if (! property_exists($block->data, 'file') || ! \is_object($block->data->file)) {
            return;
        }

        $title = '';
        if (property_exists($block->data, 'title')) {
            $title = $block->data->title;
        }

        $url = '';
        $name = '';
        $size = '';
        if (property_exists($block->data->file, 'url')) {
            $url = $block->data->file->url;
        }

        if (property_exists($block->data->file, 'name')) {
            $name = $block->data->file->name;
        }

        if (property_exists($block->data->file, 'size')) {
            $size = $block->data->file->size;
        }

        $newData = new stdClass();
        $newData->title = $title;

        $newFile = new stdClass();
        $newFile->url = $url;
        $newFile->name = $name;
        $newFile->size = $size;

        $newData->file = $newFile;
        $block->data = $newData;
    }
}
