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

    /** @var array<string, true> translation pairs that were explicitly added (format: "refA|refB") */
    private array $addedTranslationPairs = [];

    /** @var array<int, string> Map of ID => relative file path that claimed it (for duplicate detection) */
    private array $idToFileMap = [];

    /** @var array<string, Page> slug => Page index for O(1) lookups */
    private array $slugIndex = [];

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

        $content = $this->filesystem->readFile($filePath);

        // Strip UTF-8 BOM if present
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        return YamlFrontMatter::parse($content);
    }

    /**
     * @return non-empty-string
     */
    public function getSlug(string $filePath, Document $document): string
    {
        $slug = $document->matter('slug');
        $filePathSlug = $this->filePathToSlug($filePath);

        if (is_int($slug) || is_float($slug)) {
            $slug = (string) $slug;
        }

        if (! is_string($slug) || '' === $slug) {
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
        $data = \is_array($data) ? $data : throw new Exception(\sprintf('Failed to parse front matter in "%s": expected array, got %s', $filePath, get_debug_type($data)));
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
        $slug = preg_replace('/\.md$/i', '', str_replace($this->getContentDir().'/', '', $filePath)) ?? throw new Exception(\sprintf('Failed to extract slug from file path "%s"', $filePath));

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
                    if (! \is_string($value)) {
                        throw new LogicException(\sprintf('Property "%s" in page "%s" must be a slug string, got %s (%s)', $property, $slug, get_debug_type($value), var_export($value, true)));
                    }

                    $setter = 'set'.ucfirst($property);
                    /** @phpstan-ignore-next-line */
                    $page->$setter($this->getPage($value));

                    continue;
                }

                if (Media::class === $object) {
                    $setter = 'set'.ucfirst($property);
                    if (! \is_string($value)) {
                        throw new LogicException(\sprintf('Expected string value for media property "%s", got %s', $property, get_debug_type($value)));
                    }

                    $mediaName = preg_replace('@^/?media/(default)?/@', '', $value) ?? throw new Exception(\sprintf('Failed to extract media name from "%s" for property "%s"', $value, $property));
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
     * Supports cross-host references using the format "host/slug" (e.g. "other.dev/my-article").
     * The part before the first "/" is checked against registered hosts to distinguish from nested slugs.
     *
     * @param string[] $newTranslationRefs
     */
    private function syncTranslations(Page $page, array $newTranslationRefs): void
    {
        $currentTranslations = $page->getTranslations()->toArray();
        $currentRefs = array_map($this->buildTranslationRef(...), $currentTranslations);
        $pageSlug = $page->getSlug();

        $toAdd = array_diff($newTranslationRefs, $currentRefs);
        $toRemove = array_diff($currentRefs, $newTranslationRefs);

        // First, add new translations and mark them
        foreach ($toAdd as $ref) {
            $translationPage = $this->resolveTranslationRef($ref);
            if (! $translationPage instanceof Page) {
                $this->logger->warning('Translation `{ref}` not found for page `{slug}`', ['ref' => $ref, 'slug' => $pageSlug]);

                continue;
            }

            $page->addTranslation($translationPage);
            $this->markTranslationAsAdded($pageSlug, $ref);
        }

        // Then, remove translations only if they weren't added by another page
        foreach ($toRemove as $ref) {
            // Don't remove a translation that was explicitly added earlier in this import
            if ($this->wasTranslationAdded($pageSlug, $ref)) {
                continue;
            }

            $translationPage = $this->resolveTranslationRef($ref);
            if ($translationPage instanceof Page) {
                $page->removeTranslation($translationPage);
            }
        }
    }

    /**
     * Resolve a translation reference that may be a simple slug or a cross-host "host/slug" reference.
     */
    private function resolveTranslationRef(string $ref): ?Page
    {
        $slashPos = strpos($ref, '/');
        if (false !== $slashPos) {
            $possibleHost = substr($ref, 0, $slashPos);
            if ($this->apps->isKnownHost($possibleHost)) {
                $slug = substr($ref, $slashPos + 1);

                return $this->pageRepo->findOneBy(['slug' => $slug, 'host' => $possibleHost]);
            }
        }

        return $this->getPage($ref);
    }

    /**
     * Build a translation reference string for a page.
     * Returns "host/slug" for cross-host pages, or just "slug" for same-host pages.
     */
    private function buildTranslationRef(Page $page): string
    {
        $currentHost = $this->apps->get()->getMainHost();
        if ($page->host !== $currentHost) {
            return $page->host.'/'.$page->getSlug();
        }

        return $page->getSlug();
    }

    /**
     * Mark a translation pair as explicitly added during this import cycle.
     */
    private function markTranslationAsAdded(string $refA, string $refB): void
    {
        // Store both directions to catch the pair regardless of order
        $this->addedTranslationPairs[$refA.'|'.$refB] = true;
        $this->addedTranslationPairs[$refB.'|'.$refA] = true;
    }

    /**
     * Check if a translation pair was explicitly added during this import cycle.
     */
    private function wasTranslationAdded(string $refA, string $refB): bool
    {
        return isset($this->addedTranslationPairs[$refA.'|'.$refB]);
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
            throw new Exception(\sprintf('Expected slug (string) for getPage(), got %s (%s)', get_debug_type($criteria), var_export($criteria, true)));
        }

        // Check freshly imported pages first (not yet flushed to DB)
        if (isset($this->pageList[$criteria])) {
            return $this->pageList[$criteria];
        }

        // Then check slug index (O(1) lookup from DB-loaded pages)
        $this->getPages(); // ensure slugIndex is populated

        return $this->slugIndex[$criteria] ?? null;
    }

    /**
     * @return Page[]
     */
    private function getPages(bool $cache = true): array
    {
        if ($cache && null !== $this->pages) {
            return $this->pages;
        }

        $this->pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());

        $this->slugIndex = [];
        foreach ($this->pages as $page) {
            $this->slugIndex[$page->getSlug()] = $page;
        }

        return $this->pages;
    }

    /**
     * Get the loaded pages from the last getPages() call.
     * Returns null if pages haven't been loaded yet.
     *
     * @return Page[]|null
     */
    public function getLoadedPages(): ?array
    {
        return $this->pages;
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
        $this->slugIndex = [];
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
