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

    protected $mediaClass;

    public function setContentDirFinder(FlatFileContentDirFinder $contentDirFinder)
    {
        $this->contentDirFinder = $contentDirFinder;
    }

    public function setMediaClass(string $mediaClass)
    {
        $this->mediaClass = $mediaClass;
    }

    private function getContentDir()
    {
        $host = $this->apps->get()->getMainHost();

        return $this->contentDirFinder->get($host);
    }

    public function import(string $filePath, DateTimeInterface $lastEditDatetime)
    {
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

    private function editPage(string $slug, array $data, string $content, DateTime $lastEditDatetime)
    {
        $page = $this->getPage($slug);
        $newPage = false;

        if (! $page) {
            $pageClass = $this->entityClass;
            $page = new $pageClass();
            $newPage = true;
        } elseif ($page->getUpdatedAt() >= $lastEditDatetime) {
            return; // no update needed
        }
        $page->setCustomProperties([]);

        foreach ($data as $key => $value) {
            if (\in_array($key, $this->getObjectRequiredProperties())) {
                $this->toAddAtTheEnd[$slug] = array_merge($this->toAddAtTheEnd[$slug] ?? [], [$key => $value]);
            }
            $setter = 'set'.ucfirst($key);
            if (method_exists($page, $setter)) {
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
        if (true === $newPage) {
            $this->em->persist($page);
        }
    }

    private function toAddAtTheEnd()
    {
        foreach ($this->toAddAtTheEnd as $slug => $data) {
            $page = $this->getPage($slug);
            foreach ($data as $property => $value) {
                $object = $this->getObjectRequiredProperties($property);

                if (\is_string($object)) {
                    $this->$object($page, $property, $value);

                    continue;
                }

                if (PageInterface::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    $page->$setter($this->getPage($value));

                    continue;
                }

                if (MediaInterface::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    $media = $this->getMedia($value);
                    if (null === $media) {
                        continue;
                    }
                    $page->$setter($media);

                    continue;
                }
            }
        }
    }

    public function addImages(PageInterface $page, string $property, array $images)
    {
        // todo
    }

    public function addPages(PageInterface $page, string $property, array $pages)
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
