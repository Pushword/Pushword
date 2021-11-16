<?php

namespace Pushword\Flat\Importer;

use DateTime;
use DateTimeInterface;
use Exception;
use LogicException;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Utils\F;
use Pushword\Flat\FlatFileContentDirFinder;
use Spatie\YamlFrontMatter\YamlFrontMatter;

/**
 * Permit to find error in image or link.
 *
 * @extends AbstractImporter<PageInterface>
 */
class PageImporter extends AbstractImporter
{
    /**
     * @var PageInterface[]|null
     */
    protected ?array $pages = null;

    /**
     * @var array<string, array<mixed>>
     */
    protected array $toAddAtTheEnd = [];

    protected ?\Pushword\Flat\FlatFileContentDirFinder $contentDirFinder = null;

    /**
     * @var class-string<MediaInterface>
     */
    protected string $mediaClass;

    private bool $newPage = false;

    public function getContentDirFinder(): FlatFileContentDirFinder
    {
        if (null === $this->contentDirFinder) {
            throw new LogicException();
        }

        return $this->contentDirFinder;
    }

    public function setContentDirFinder(FlatFileContentDirFinder $flatFileContentDirFinder): void
    {
        $this->contentDirFinder = $flatFileContentDirFinder;
    }

    /**
     * @param class-string<MediaInterface> $mediaClass
     */
    public function setMediaClass(string $mediaClass): void
    {
        $this->mediaClass = $mediaClass;
    }

    private function getContentDir(): string
    {
        $host = $this->apps->get()->getMainHost();

        return $this->getContentDirFinder()->get($host);
    }

    public function import(string $filePath, DateTimeInterface $dateTime): void
    {
        if (! str_starts_with((string) finfo_file(\Safe\finfo_open(\FILEINFO_MIME_TYPE), $filePath), 'text/')) {
            return;
        }

        $content = \Safe\file_get_contents($filePath);
        $document = YamlFrontMatter::parse($content);

        if (empty($document->matter())) { // @phpstan-ignore-line
            return; //throw new Exception('No content found in `'.$filePath.'`');
        }

        $slug = $document->matter('slug') ?? $this->filePathToSlug($filePath);

        $this->editPage($slug, $document->matter(), $document->body(), $dateTime);
    }

    private function filePathToSlug(string $filePath): string
    {
        $slug = F::preg_replace_str('/\.md$/i', '', str_replace($this->getContentDir().'/', '', $filePath));

        if ('index' == $slug) {
            $slug = 'homepage';
        } elseif ('index' == basename($slug)) {
            $slug = \Safe\substr($slug, 0, -\strlen('index'));
        }

        return Page::normalizeSlug($slug);
    }

    private function getPageFromSlug(string $slug): PageInterface
    {
        $page = $this->getPage($slug);
        $this->newPage = false;

        if (null === $page) {
            $pageClass = $this->entityClass;
            $page = new $pageClass();
            $this->newPage = true;
        }

        return $page;
    }

    /**
     * @param \DateTime|\DateTimeImmutable $dateTime
     * @param mixed[]                      $data
     */
    private function editPage(string $slug, array $data, string $content, DateTimeInterface $dateTime): void
    {
        $page = $this->getPageFromSlug($slug);

        if (! $this->newPage && $page->getUpdatedAt() >= $dateTime) {
            return; // no update needed
        }

        $page->setCustomProperties([]);

        foreach ($data as $key => $value) {
            $key = $this->normalizePropertyName($key);
            $camelKey = self::underscoreToCamelCase($key);

            if (\array_key_exists($camelKey, $this->getObjectRequiredProperties())) {
                $this->toAddAtTheEnd[$slug] = array_merge($this->toAddAtTheEnd[$slug] ?? [], [$camelKey => $value]);

                continue;
            }

            $setter = 'set'.ucfirst($camelKey);
            if (method_exists($page, $setter)) {
                if (\in_array($camelKey, ['publishedAt', 'createdAt', 'updatedAt'], true)) {
                    $value = new DateTime(\strval($value));
                }

                $page->$setter($value); // @phpstan-ignore-line

                continue;
            }

            $page->setCustomProperty($key, $value);
        }

        $page->setHost($this->apps->get()->getMainHost());
        $page->setSlug($slug);
        if ('' === $page->getLocale() || '0' === $page->getLocale()) {
            $page->setLocale($this->apps->get()->getLocale());
        }

        $page->setMainContent($content);

        if ($this->newPage) {
            $this->em->persist($page);
        }
    }

    private function normalizePropertyName(string $propertyName): string
    {
        if ('parent' == $propertyName) {
            $propertyName = 'parentPage';
        }

        return $propertyName;
    }

    private function toAddAtTheEnd(): void
    {
        foreach ($this->toAddAtTheEnd as $slug => $data) {
            $page = $this->getPage($slug);
            foreach ($data as $property => $value) {
                $object = $this->getObjectRequiredProperty($property);

                if (PageInterface::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    $page->$setter($this->getPage($value)); // @phpstan-ignore-line

                    continue;
                }

                if (MediaInterface::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    if (! \is_string($value)) {
                        throw new LogicException();
                    }

                    $mediaName = F::preg_replace_str('@^/?media/(default)?/@', '', $value);
                    $media = $this->getMedia($mediaName);
                    if (null === $media) {
                        throw new Exception('Media `'.$value.'` ('.$mediaName.') not found in `'.$slug.'`.');
                    }

                    $page->$setter($media); // @phpstan-ignore-line

                    continue;
                }

                $this->$object($page, $property, $value); // @phpstan-ignore-line
            }
        }
    }

    /**
     * @param string[] $pages
     */
    private function addPages(PageInterface $page, string $property, array $pages): void
    {
        $setter = 'set'.ucfirst($property);
        $this->$setter([]); // @phpstan-ignore-line
        foreach ($pages as $p) {
            $adder = 'add'.ucfirst($property);
            $page->$adder($this->getPage($p)); // @phpstan-ignore-line
        }
    }

    public function finishImport(): void
    {
        $this->em->flush();

        $this->getPages(false);
        $this->toAddAtTheEnd();

        $this->em->flush();
    }

    /**
     * Todo, get them automatically.
     *
     * @return array<string, (string|class-string)>
     */
    private function getObjectRequiredProperties(): array
    {
        $properties = [
            'extendedPage' => PageInterface::class,
            'parentPage' => PageInterface::class,
            'translations' => 'addPages',
            'mainImage' => MediaInterface::class,
        ];

        return $properties;
    }

    /**
     * @return string|class-string
     */
    private function getObjectRequiredProperty(string $key): string
    {
        $properties = $this->getObjectRequiredProperties();

        return $properties[$key];
    }

    private function getMedia(string $media): ?MediaInterface
    {
        return Repository::getMediaRepository($this->em, $this->mediaClass)->findOneBy(['media' => $media]);
    }

    /**
     * @param string|array<string, string> $criteria
     */
    private function getPage($criteria): ?PageInterface
    {
        if (\is_array($criteria)) {
            return Repository::getPageRepository($this->em, $this->entityClass)->findOneBy($criteria);
        }

        $pages = array_filter($this->getPages(), fn (PageInterface $page): bool => $page->getSlug() == $criteria);
        $pages = array_values($pages);

        return $pages[0] ?? null;
    }

    /**
     * @return PageInterface[]
     */
    private function getPages(bool $cache = true): array
    {
        if ($cache && null !== $this->pages) {
            return $this->pages;
        }

        $repo = Repository::getPageRepository($this->em, $this->entityClass);

        return $this->pages = $repo->findByHost($this->apps->get()->getMainHost());
    }
}
