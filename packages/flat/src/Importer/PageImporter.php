<?php

namespace Pushword\Flat\Importer;

use DateTime;
use DateTimeInterface;
use Exception;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Pushword\Flat\FlatFileContentDirFinder;
use Spatie\YamlFrontMatter\YamlFrontMatter;

/**
 * Permit to find error in image or link.
 */
class PageImporter extends AbstractImporter
{
    /** @var array */
    protected $pages;

    protected $toAddAtTheEnd = [];

    /** @var FlatFileContentDirFinder */
    protected $contentDirFinder;

    protected string $mediaClass;
    protected string $pageHasMediaClass;

    private bool $newPage = false;

    public function setContentDirFinder(FlatFileContentDirFinder $contentDirFinder)
    {
        $this->contentDirFinder = $contentDirFinder;
    }

    public function setMediaClass(string $mediaClass)
    {
        $this->mediaClass = $mediaClass;
    }

    public function setPageHasMediaClass(string $class): void
    {
        $this->pageHasMediaClass = $class;
    }

    private function getContentDir()
    {
        $host = $this->apps->get()->getMainHost();

        return $this->contentDirFinder->get($host);
    }

    public function import(string $filePath, DateTimeInterface $lastEditDatetime): void
    {
        if ('text/plain' != finfo_file(finfo_open(FILEINFO_MIME_TYPE), $filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        $yamlParsed = YamlFrontMatter::parse($content);

        if (empty($yamlParsed->matter())) {
            return; //throw new Exception('No content found in `'.$filePath.'`');
        }

        $slug = $yamlParsed->matter('slug') ?? $this->filePathToSlug($filePath);

        $this->editPage($slug, $yamlParsed->matter(), $yamlParsed->body(), $lastEditDatetime);
    }

    private function filePathToSlug($filePath): string
    {
        $slug = preg_replace('/\.md$/i', '', str_replace($this->getContentDir().'/', '', $filePath));

        if ('index' == $slug) {
            $slug = 'homepage';
        } elseif ('index' == basename($slug)) {
            $slug = substr($slug, 0, -\strlen('index'));
        }

        $slug = Page::normalizeSlug($slug);

        return $slug;
    }

    private function getPageFromSlug($slug): PageInterface
    {
        $page = $this->getPage($slug);
        $this->newPage = false;

        if (! $page) {
            $pageClass = $this->entityClass;
            $page = new $pageClass();
            $this->newPage = true;
        }

        return $page;
    }

    private function editPage(string $slug, array $data, string $content, DateTime $lastEditDatetime)
    {
        $page = $this->getPageFromSlug($slug);

        if (false === $this->newPage && $page->getUpdatedAt() >= $lastEditDatetime) {
            return; // no update needed
        }

        $page->setCustomProperties([]);

        foreach ($data as $key => $value) {
            $key = $this->normalizePropertyName($key);
            $camelKey = self::underscoreToCamelCase($key);

            if (\in_array($camelKey, array_keys($this->getObjectRequiredProperties()))) {
                $this->toAddAtTheEnd[$slug] = array_merge($this->toAddAtTheEnd[$slug] ?? [], [$camelKey => $value]);

                continue;
            }
            $setter = 'set'.ucfirst($camelKey);
            if (method_exists($page, $setter)) {
                if (in_array($camelKey, ['createdAt', 'updatedAt']))
                    $value = new DateTime($value);

                $page->$setter($value);

                continue;
            }
            $page->setCustomProperty($key, $value);
        }

        $page->setHost($this->apps->get()->getMainHost());
        $page->setSlug($slug);
        if (! $page->getLocale()) {
            $page->setLocale($this->apps->get()->getLocale());
        }
        $page->setMainContent($content);

        // todo parentPage, translations
        if (true === $this->newPage) {
            $this->em->persist($page);
        }
    }

    private function normalizePropertyName(string $propertyName)
    {
        if ('parent' == $propertyName) {
            $propertyName = 'parentPage';
        }

        return $propertyName;
    }

    private function toAddAtTheEnd()
    {
        foreach ($this->toAddAtTheEnd as $slug => $data) {
            $page = $this->getPage($slug);
            foreach ($data as $property => $value) {
                $object = $this->getObjectRequiredProperties($property);

                if (PageInterface::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    $page->$setter($this->getPage($value));

                    continue;
                }

                if (MediaInterface::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    $mediaName = preg_replace('@^/?media/(default)?/@', '', $value);
                    $media = $this->getMedia($mediaName);
                    if (null === $media) {
                        throw new Exception('Media `'.$value.'` ('.$mediaName.') not found in `'.$slug.'`.');
                    }
                    $page->$setter($media);

                    continue;
                }

                $this->$object($page, $property, $value);
            }
        }
    }

    private function addImages(PageInterface $page, string $property, array $images): void
    {
        $page->resetPageHasMedias();

        foreach ($images as $image) {
            $mediaName = preg_replace('@^/?media/(default)?/@', '', $image);
            $media = $this->getMedia($mediaName);
            if (null === $media) {
                throw new Exception('Media `'.$image.'` ('.$mediaName.') not found in `'.$page->getSlug().'`.');
            }
            $pageHasMediaClass = $this->pageHasMediaClass;
            $pageHasMedia = (new $pageHasMediaClass())->setMedia($media);
            $page->addPageHasMedia($pageHasMedia);
        }
    }

    private function addPages(PageInterface $page, string $property, array $pages)
    {
        $setter = 'set'.ucfirst($property);
        $this->$setter([]);
        foreach ($pages as $p) {
            $adder = 'add'.ucfirst($property);
            $page->$adder($this->getPage($p));
        }
    }

    public function finishImport()
    {
        $this->em->flush();

        $this->getPages(false);
        $this->toAddAtTheEnd();

        $this->em->flush();
    }

    /**
     * Todo, get them automatically.
     *
     * @return array|string
     */
    private function getObjectRequiredProperties($key = null)
    {
        $properties = [
            'parentPage' => PageInterface::class,
            'translations' => 'addPages',
            'mainImage' => MediaInterface::class,
            'images' => 'addImages',
        ];

        if (null === $key) {
            return $properties;
        }

        return $properties[$key];
    }

    private function getMedia($media)
    {
        return Repository::getMediaRepository($this->em, $this->mediaClass)->findOneBy(['media' => $media]);
    }

    private function getPage($slug): ?PageInterface
    {
        if (\is_array($slug)) {
            return Repository::getPageRepository($this->em, $this->entityClass)->findOneBy($slug);
        }

        $pages = array_filter($this->getPages(), function ($page) use ($slug) { return $page->getSlug() == $slug; });
        $pages = array_values($pages);

        return $pages[0] ?? null;
    }

    private function getPages($cache = true): array
    {
        if (true === $cache && $this->pages) {
            return $this->pages;
        }
        $repo = Repository::getPageRepository($this->em, $this->entityClass);

        return $this->pages = $repo->findByHost($this->apps->get()->getMainHost(), $this->apps->get()->isFirstApp());
    }
}
