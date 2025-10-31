<?php

namespace Pushword\Flat\Importer;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use LogicException;
use Override;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Flat\FlatFileContentDirFinder;

use function Safe\file_get_contents;

use Spatie\YamlFrontMatter\Document;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Permit to find error in image or link.
 *
 * @extends AbstractImporter<Page>
 */
final class PageImporter extends AbstractImporter
{
    /**
     * @var Page[]|null
     */
    protected ?array $pages = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $toAddAtTheEnd = [];

    #[Required]
    public FlatFileContentDirFinder $contentDirFinder;

    #[Required]
    public PageRepository $pageRepo;

    private bool $newPage = false;

    /** @var array<string, string> where key is the relative file path and value is the slug */
    private array $slugs = [];

    /** @var array<string, Page> where key is the slug */
    private array $pageList = [];

    private function getContentDir(): string
    {
        $host = $this->apps->get()->getMainHost();

        return $this->contentDirFinder->get($host);
    }

    public function getDocumentFromFile(string $filePath): ?Document
    {
        if (! str_starts_with($this->getMimeTypeFromFile($filePath), 'text/')) {
            return null;
        }

        $content = file_get_contents($filePath);
        $document = YamlFrontMatter::parse($content);

        return $document;
    }

    /**
     * @return non-empty-string
     */
    public function getSlug(string $filePath, Document $document): string
    {
        $slug = $document->matter('slug');
        $filePathSlug = $this->filePathToSlug($filePath);

        if (! is_string($slug) || in_array($slug, ['', null], true)) {
            return $filePathSlug;
        }

        if ($slug !== $filePathSlug) {
            // todo : throw and exception
        }

        return $slug;
    }

    public function import(string $filePath, DateTimeInterface $lastEditDateTime): void
    {
        $document = $this->getDocumentFromFile($filePath);
        if (null === $document) {
            return;
        }

        $slug = $this->getSlug($filePath, $document);

        $relativeFilePath = str_replace($this->getContentDir().'/', '', $filePath);
        $this->slugs[$relativeFilePath] = $slug;

        $data = $document->matter();
        $data = \is_array($data) ? $data : throw new Exception();
        /** @var array<string, mixed> $data */
        $this->pageList[$slug] = $this->editPage($slug, $data, $document->body(), $lastEditDateTime);
    }

    /**
     * @return non-empty-string
     */
    public function filePathToSlug(string $filePath): string
    {
        $slug = preg_replace('/\.md$/i', '', str_replace($this->getContentDir().'/', '', $filePath)) ?? throw new Exception();

        if ('index' === $slug) {
            $slug = 'homepage';
        } elseif ('index' === basename($slug)) {
            $slug = substr($slug, 0, -\strlen('index'));
        }

        return Page::normalizeSlug($slug);
    }

    private function getPageFromSlug(string $slug): Page
    {
        $page = $this->getPage($slug);
        $this->newPage = false;

        if (! $page instanceof Page) {
            $initDateTimeProperties = false;
            $page = new Page($initDateTimeProperties);
            $this->newPage = true;
        }

        return $page;
    }

    private function relativeMarkdownLinkToSlugLink(): void
    {
        foreach ($this->slugs as $slug) {
            $page = $this->pageList[$slug] ?? throw new Exception($slug);

            $content = $page->getMainContent();
            // $content = preg_replace('/\[(.+)\]\(([^\/].*)\.md\)/', '[$1](/$2)', $content);
            preg_match_all('/\[(.+)\]\(([^\/].*\.md)(#?(.*))\)/', $content, $matches);
            foreach ($matches[0] as $k => $match) {
                if (! isset($this->slugs[$matches[2][$k]])) {
                    continue;
                }

                $content = str_replace($match, '['.$matches[1][$k].'](/'.$this->slugs[$matches[2][$k]].($matches[3][$k] ?? '').')', $content);
            }

            $page->setMainContent($content);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function editPage(
        string $slug,
        array $data,
        string $content,
        DateTime|DateTimeImmutable|DateTimeInterface $lastEditDateTime
    ): Page {
        if (isset($data['id'])) {
            $page = $this->pageRepo->find($data['id']);
            unset($data['id']);
        }

        $page ??= $this->getPageFromSlug($slug);

        if (! $this->newPage && $page->getUpdatedAt() >= $lastEditDateTime) {
            return $page; // no update needed
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
                if (\in_array($camelKey, ['publishedAt', 'createdAt', 'updatedAt'], true) && \is_scalar($value)) {
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
            $this->initDateTimeProperties($page, $lastEditDateTime);
            $this->em->persist($page);
        }

        return $page;
    }

    private function initDateTimeProperties(Page $page, DateTimeInterface $lastEditDateTime): void
    {
        if (null === $page->getPublishedAt(false)) {
            $page->setPublishedAt($lastEditDateTime);
        }

        if (null === $page->getCreatedAt(false)) {
            $page->setCreatedAt($lastEditDateTime);
        }

        if (null === $page->getUpdatedAt(false)) {
            $page->setUpdatedAt($lastEditDateTime);
        }
    }

    private function normalizePropertyName(string $propertyName): string
    {
        if ('parent' === $propertyName) {
            return 'parentPage';
        }

        return $propertyName;
    }

    private function toAddAtTheEnd(): void
    {
        foreach ($this->toAddAtTheEnd as $slug => $data) {
            $page = $this->getPage($slug);
            foreach ($data as $property => $value) {
                $object = $this->getObjectRequiredProperty($property);

                if (Page::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    /** @phpstan-ignore-next-line */
                    $page->$setter($this->getPage($value));

                    continue;
                }

                if (Media::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    if (! \is_string($value)) {
                        throw new LogicException();
                    }

                    $mediaName = preg_replace('@^/?media/(default)?/@', '', $value) ?? throw new Exception();
                    $media = $this->getMedia($mediaName);
                    if (! $media instanceof Media) {
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
    private function addPages(Page $page, string $property, array $pages): void
    {
        $setter = 'set'.ucfirst($property);
        $this->$setter([]); // @phpstan-ignore-line
        foreach ($pages as $p) {
            $adder = 'add'.ucfirst($property);
            $page->$adder($this->getPage($p)); // @phpstan-ignore-line
        }
    }

    #[Override]
    public function finishImport(): void
    {
        $this->relativeMarkdownLinkToSlugLink();

        $this->em->flush();

        $this->getPages(false);
        $this->toAddAtTheEnd();

        $this->em->flush();
    }

    /**
     * Todo, get them automatically.
     *
     * @return array{extendedPage: class-string<Page>, parentPage: class-string<Page>, translations: string, mainImage: class-string<Media>}
     */
    private function getObjectRequiredProperties(): array
    {
        return [
            'extendedPage' => Page::class,
            'parentPage' => Page::class,
            'translations' => 'addPages',
            'mainImage' => Media::class,
        ];
    }

    /**
     * @return string|class-string
     */
    private function getObjectRequiredProperty(string $key): string
    {
        $properties = $this->getObjectRequiredProperties();

        return $properties[$key];
    }

    private function getMedia(string $media): ?Media
    {
        return $this->em->getRepository(Media::class)->findOneBy(['media' => $media]);
    }

    private function getPage(mixed $criteria): ?Page
    {
        if (\is_array($criteria)) {
            /** @var array<string, mixed> $criteria */
            return $this->em->getRepository(Page::class)->findOneBy($criteria);
        }

        if (! \is_string($criteria)) {
            throw new Exception();
        }

        $pages = array_filter($this->getPages(), static fn (Page $page): bool => $page->getSlug() === $criteria);
        $pages = array_values($pages);

        return $pages[0] ?? null;
    }

    /**
     * @return Page[]
     */
    private function getPages(bool $cache = true): array
    {
        if ($cache && null !== $this->pages) {
            return $this->pages;
        }

        return $this->pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());
    }
}
