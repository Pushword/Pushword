<?php

namespace Pushword\Flat\Importer;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use LogicException;
use Override;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Flat\Converter\PropertyConverterRegistry;
use Pushword\Flat\Converter\PublishedAtConverter;
use Pushword\Flat\FlatFileContentDirFinder;
use Spatie\YamlFrontMatter\Document;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Filesystem\Filesystem;
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

    #[Required]
    public MediaRepository $mediaRepo;

    #[Required]
    public LoggerInterface $logger;

    #[Required]
    public PropertyConverterRegistry $converterRegistry;

    private Filesystem $filesystem {
        get => $this->filesystem ??= new Filesystem();
    }

    private bool $newPage = false;

    private int $importedCount = 0;

    private int $skippedCount = 0;

    /** @var array<string, string> where key is the relative file path and value is the slug */
    private array $slugs = [];

    /** @var array<string, Page> where key is the slug */
    private array $pageList = [];

    /** @var array<string, true> translation pairs that were explicitly added (format: "slugA|slugB") */
    private array $addedTranslationPairs = [];

    /** @var array<int, string> Map of ID => relative file path that claimed it (for duplicate detection) */
    private array $idToFileMap = [];

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

        $filename = basename($filePath);
        if (str_ends_with($filePath, 'pages.csv')
          || str_ends_with($filePath, 'medias.csv')
          || str_ends_with($filePath, 'conversation.csv')
          || str_ends_with($filePath, 'redirection.csv')
          || 1 === preg_match('/^index(\.[a-z]{2}(-[a-zA-Z]{2,})?)?\.csv$/', $filename)
          || 1 === preg_match('/^iDraft(\.[a-z]{2}(-[a-zA-Z]{2,})?)?\.csv$/', $filename)
        ) {
            return null;
        }

        return YamlFrontMatter::parse($this->filesystem->readFile($filePath));
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
            // todo : throw and exception ?
            $this->logger->warning('Slug mismatch for file `{filePath}` and slug `{slug}`', [
                'filePath' => $filePath,
                'slug' => $slug,
            ]);
        }

        return $slug;
    }

    #[Override]
    public function import(string $filePath, DateTimeInterface $lastEditDateTime): bool
    {
        $document = $this->getDocumentFromFile($filePath);
        if (null === $document) {
            return false;
        }

        $slug = $this->getSlug($filePath, $document);

        $relativeFilePath = str_replace($this->getContentDir().'/', '', $filePath);
        $this->slugs[$relativeFilePath] = $slug;

        $data = $document->matter();
        $data = \is_array($data) ? $data : throw new Exception();
        /** @var array<string, mixed> $data */
        $previousImportedCount = $this->importedCount;
        $this->pageList[$slug] = $this->editPage($slug, $data, $document->body(), $lastEditDateTime, $relativeFilePath);

        // Return true if this file was actually imported (not skipped)
        return $this->importedCount > $previousImportedCount;
    }

    /**
     * @return non-empty-string
     */
    private function filePathToSlug(string $filePath): string
    {
        $slug = preg_replace('/\.md$/i', '', str_replace($this->getContentDir().'/', '', $filePath)) ?? throw new Exception();

        if ('index' === $slug) {
            $slug = 'homepage';
        } elseif ('index' === basename($slug)) {
            $slug = substr($slug, 0, -\strlen('index'));
        }

        $slug = Page::normalizeSlug($slug);
        assert('' !== $slug);

        return $slug;
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
        DateTime|DateTimeImmutable|DateTimeInterface $lastEditDateTime,
        string $relativeFilePath = '',
    ): Page {
        if (isset($data['id']) && is_numeric($data['id'])) {
            $id = (int) $data['id'];

            if (isset($this->idToFileMap[$id])) {
                $this->logger->warning(
                    'Duplicate ID {id} in {file}, already used by {original} - ID ignored',
                    ['id' => $id, 'file' => $relativeFilePath, 'original' => $this->idToFileMap[$id]]
                );
            } else {
                $this->idToFileMap[$id] = $relativeFilePath;
                $page = $this->pageRepo->find($id);
            }

            unset($data['id']);
        }

        $page ??= $this->getPageFromSlug($slug);

        if (! $this->newPage && $page->updatedAt >= $lastEditDateTime) {
            ++$this->skippedCount;

            return $page; // no update needed
        }

        $this->logger->info('Importing page `'.$slug.'` ('.($this->newPage ? 'new' : $page->id).')');
        ++$this->importedCount;

        $page->setCustomProperties([]);
        $publishedAtExplicitlySet = false;

        foreach ($data as $key => $value) {
            $key = $this->normalizePropertyName($key);
            $camelKey = self::underscoreToCamelCase($key);

            if (\array_key_exists($camelKey, $this->getObjectRequiredProperties())) {
                $this->toAddAtTheEnd[$slug] = array_merge($this->toAddAtTheEnd[$slug] ?? [], [$camelKey => $value]);

                continue;
            }

            if ('locale' === $camelKey) {
                $page->locale = \is_scalar($value) ? (string) $value : '';

                continue;
            }

            $setter = 'set'.ucfirst($camelKey);
            if (method_exists($page, $setter)) {
                if ('publishedAt' === $camelKey) {
                    $value = PublishedAtConverter::fromFlatValue($value);
                    $publishedAtExplicitlySet = true;
                } elseif (\in_array($camelKey, ['createdAt', 'updatedAt'], true) && \is_scalar($value)) {
                    $value = new DateTime((string) $value);
                }

                $page->$setter($value); // @phpstan-ignore-line

                continue;
            }

            $page->setCustomProperty($key, $this->converterRegistry->fromFlatValue($key, $value));
        }

        $page->host = $this->apps->get()->getMainHost();
        $page->setSlug($slug);
        if ('' === $page->locale || '0' === $page->locale) {
            $page->locale = $this->apps->get()->getLocale();
        }

        $page->setMainContent($content);

        if ($this->newPage) {
            $this->initDateTimeProperties($page, $lastEditDateTime, $publishedAtExplicitlySet);
            $this->em->persist($page);
        }

        return $page;
    }

    private function initDateTimeProperties(Page $page, DateTimeInterface $lastEditDateTime, bool $publishedAtExplicitlySet = false): void
    {
        if (! $publishedAtExplicitlySet && null === $page->getPublishedAt()) {
            $page->setPublishedAt($lastEditDateTime);
        }

        if (null === $page->getCreatedAtNullable()) {
            $page->createdAt = $lastEditDateTime;
        }

        if (null === $page->getUpdatedAtNullable()) {
            $page->updatedAt = $lastEditDateTime;
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
            if (null === $page) {
                $this->logger->warning('Page `{slug}` not found when processing deferred properties', ['slug' => $slug]);

                continue;
            }

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
                    $media = $this->mediaRepo->findOneByFileName($mediaName);
                    if (! $media instanceof Media) {
                        $this->logger->warning('Media `{value}` ({mediaName}) not found in `{slug)}`, skipping', [
                            'value' => $value,
                            'mediaName' => $mediaName,
                            'slug' => $slug,
                        ]);

                        continue;
                    }

                    $page->$setter($media); // @phpstan-ignore-line

                    continue;
                }

                $this->$object($page, $property, $value); // @phpstan-ignore-line
            }
        }
    }

    /**
     * @param string[] $pageSlugs
     */
    private function addPages(Page $page, string $property, array $pageSlugs): void
    {
        if ('translations' === $property) {
            $this->syncTranslations($page, $pageSlugs);

            return;
        }

        $setter = 'set'.ucfirst($property);
        $page->$setter([]); // @phpstan-ignore-line
        foreach ($pageSlugs as $p) {
            $adder = 'add'.ucfirst($property);
            $page->$adder($this->getPage($p)); // @phpstan-ignore-line
        }
    }

    /**
     * Sync translations for a page, handling additions and removals.
     *
     * Addition takes precedence: if page A adds B, the link is created even if B.md doesn't list A.
     * Removal only happens if no other page added the translation during this import cycle.
     *
     * @param string[] $newTranslationSlugs
     */
    private function syncTranslations(Page $page, array $newTranslationSlugs): void
    {
        $currentTranslations = $page->getTranslations()->toArray();
        $currentSlugs = array_map(static fn (Page $p): string => $p->getSlug(), $currentTranslations);
        $pageSlug = $page->getSlug();

        $toAdd = array_diff($newTranslationSlugs, $currentSlugs);
        $toRemove = array_diff($currentSlugs, $newTranslationSlugs);

        // First, add new translations and mark them
        foreach ($toAdd as $slug) {
            $translationPage = $this->getPage($slug);
            if ($translationPage instanceof Page) {
                $page->addTranslation($translationPage);
                $this->markTranslationAsAdded($pageSlug, $slug);
            }
        }

        // Then, remove translations only if they weren't added by another page
        foreach ($toRemove as $slug) {
            // Don't remove a translation that was explicitly added earlier in this import
            if ($this->wasTranslationAdded($pageSlug, $slug)) {
                continue;
            }

            $translationPage = $this->getPage($slug);
            if ($translationPage instanceof Page) {
                $page->removeTranslation($translationPage);
            }
        }
    }

    /**
     * Mark a translation pair as explicitly added during this import cycle.
     */
    private function markTranslationAsAdded(string $slugA, string $slugB): void
    {
        // Store both directions to catch the pair regardless of order
        $this->addedTranslationPairs[$slugA.'|'.$slugB] = true;
        $this->addedTranslationPairs[$slugB.'|'.$slugA] = true;
    }

    /**
     * Check if a translation pair was explicitly added during this import cycle.
     */
    private function wasTranslationAdded(string $slugA, string $slugB): bool
    {
        return isset($this->addedTranslationPairs[$slugA.'|'.$slugB]);
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

    /**
     * Get slugs of pages imported from .md files.
     *
     * @return string[]
     */
    public function getImportedSlugs(): array
    {
        return array_keys($this->pageList);
    }

    /**
     * Check if any pages were imported.
     */
    public function hasImportedPages(): bool
    {
        return [] !== $this->pageList;
    }

    /**
     * Reset import state for a new import cycle.
     */
    public function resetImport(): void
    {
        $this->pages = null;
        $this->toAddAtTheEnd = [];
        $this->slugs = [];
        $this->pageList = [];
        $this->addedTranslationPairs = [];
        $this->idToFileMap = [];
        $this->importedCount = 0;
        $this->skippedCount = 0;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }
}
