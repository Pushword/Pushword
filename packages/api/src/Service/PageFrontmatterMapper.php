<?php

namespace Pushword\Api\Service;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Converter\PropertyConverterRegistry;
use Pushword\Flat\Converter\PublishedAtConverter;
use Symfony\Component\Yaml\Yaml;

/**
 * Translates between the flat-file frontmatter shape (the editor-facing dict)
 * and the Page entity columns. The same key set the markdown sync uses, so a
 * payload accepted by the API is also accepted by the on-disk format.
 */
final readonly class PageFrontmatterMapper
{
    /**
     * Top-level frontmatter keys with dedicated handling below (recognized
     * columns/associations plus the export-only `revision` stamp). A key in this
     * list is never treated as a bare custom property by the fallback loop.
     *
     * @var string[]
     */
    private const array RESERVED_FRONTMATTER_KEYS = [
        'h1', 'title', 'name', 'metaRobots', 'host', 'locale', 'template',
        'editMessage', 'slug', 'weight', 'tags', 'redirectFrom', 'publishedAt',
        'holdPublication', 'mainImage', 'parentPage', 'variantOf', 'customCanonical',
        'translations', 'customProperties', 'revision',
    ];

    public function __construct(
        private PageRepository $pageRepository,
        private MediaRepository $mediaRepository,
        private SiteRegistry $siteRegistry,
        private ?PropertyConverterRegistry $converterRegistry = null,
    ) {
    }

    /**
     * @return array{frontmatter: array<string, mixed>, body: string}
     */
    public function toArray(Page $page): array
    {
        $frontmatter = [
            'h1' => $page->getH1(),
            'title' => $page->title,
            'name' => $page->name,
            'metaRobots' => $page->metaRobots,
            'host' => $page->host,
            'locale' => $page->locale,
            'template' => $page->template,
            'editMessage' => $page->editMessage,
            'slug' => $page->getSlug(),
            'weight' => $page->getWeight(),
            'tags' => $page->getTagList(),
            'redirectFrom' => $page->getRedirectFromMap(),
            'publishedAt' => $page->getPublishedAt()?->format(DateTimeInterface::ATOM),
            'holdPublication' => $page->isHoldPublication(),
            'mainImage' => $page->getMainImage()?->getFileName(),
            'parentPage' => $page->getParentPage()?->getSlug(),
            'variantOf' => $page->getVariantOf()?->getSlug(),
            'customCanonical' => $page->getCustomCanonical(),
            'translations' => array_values(array_map(
                fn (Page $t): string => $this->buildTranslationRef($t, $page->host),
                $page->getTranslations()->toArray(),
            )),
            'customProperties' => $page->getCustomProperties(),
        ];

        return [
            'frontmatter' => $frontmatter,
            'body' => $page->getMainContent(),
        ];
    }

    /**
     * Apply a frontmatter payload onto a Page.
     *
     * @param array<string, mixed> $frontmatter
     */
    public function applyFrontmatter(Page $page, array $frontmatter): void
    {
        if (\array_key_exists('h1', $frontmatter) && (\is_string($frontmatter['h1']) || null === $frontmatter['h1'])) {
            $page->setH1($frontmatter['h1'] ?? '');
        }

        if (\array_key_exists('title', $frontmatter) && (\is_string($frontmatter['title']) || null === $frontmatter['title'])) {
            $page->setTitle($frontmatter['title'] ?? '');
        }

        if (\array_key_exists('name', $frontmatter) && (\is_string($frontmatter['name']) || null === $frontmatter['name'])) {
            $page->setName($frontmatter['name'] ?? '');
        }

        if (\array_key_exists('metaRobots', $frontmatter) && (\is_string($frontmatter['metaRobots']) || null === $frontmatter['metaRobots'])) {
            $page->setMetaRobots($frontmatter['metaRobots'] ?? '');
        }

        if (\array_key_exists('host', $frontmatter) && \is_string($frontmatter['host'])) {
            $page->host = $frontmatter['host'];
        }

        if (\array_key_exists('locale', $frontmatter) && \is_string($frontmatter['locale'])) {
            $page->locale = $frontmatter['locale'];
        }

        if (\array_key_exists('template', $frontmatter) && (\is_string($frontmatter['template']) || null === $frontmatter['template'])) {
            $page->template = $frontmatter['template'];
        }

        if (\array_key_exists('editMessage', $frontmatter) && \is_string($frontmatter['editMessage'])) {
            $page->editMessage = $frontmatter['editMessage'];
        }

        if (\array_key_exists('slug', $frontmatter) && \is_string($frontmatter['slug']) && '' !== $frontmatter['slug']) {
            $page->setSlug($frontmatter['slug']);
        }

        if (\array_key_exists('weight', $frontmatter) && is_numeric($frontmatter['weight'])) {
            $page->setWeight((int) $frontmatter['weight']);
        }

        if (\array_key_exists('tags', $frontmatter) && \is_array($frontmatter['tags'])) {
            $tags = array_values(array_filter($frontmatter['tags'], is_string(...)));
            $page->setTags($tags);
        }

        if (\array_key_exists('redirectFrom', $frontmatter) && (\is_array($frontmatter['redirectFrom']) || \is_string($frontmatter['redirectFrom']))) {
            $page->setRedirectFrom($frontmatter['redirectFrom']);
        }

        if (\array_key_exists('publishedAt', $frontmatter)) {
            $page->setPublishedAt($this->parseDateTime($frontmatter['publishedAt']));
        }

        if (\array_key_exists('holdPublication', $frontmatter)) {
            $page->setHoldPublication((bool) $frontmatter['holdPublication']);
        }

        if (\array_key_exists('mainImage', $frontmatter)) {
            $page->setMainImage($this->resolveMedia($frontmatter['mainImage']));
        }

        if (\array_key_exists('parentPage', $frontmatter)) {
            $page->setParentPage($this->resolvePageRef($frontmatter['parentPage'], $page->host));
        }

        if (\array_key_exists('variantOf', $frontmatter)) {
            // Resolve the master by slug on the same host. setVariantOf() enforces
            // the single-level rule (a variant's master cannot itself be a variant
            // and a page cannot be its own master) and throws on violation.
            $page->setVariantOf($this->resolvePageRef($frontmatter['variantOf'], $page->host));
        }

        if (\array_key_exists('customCanonical', $frontmatter) && (\is_string($frontmatter['customCanonical']) || null === $frontmatter['customCanonical'])) {
            $page->setCustomCanonical($frontmatter['customCanonical']);
        }

        if (\array_key_exists('translations', $frontmatter)) {
            $this->syncTranslations($page, $frontmatter['translations']);
        }

        if (\array_key_exists('customProperties', $frontmatter) && \is_array($frontmatter['customProperties'])) {
            /** @var array<string, mixed> $custom */
            $custom = $frontmatter['customProperties'];
            $page->setCustomProperties($custom);
            foreach (array_keys($custom) as $key) {
                $page->registerManagedPropertyKey($key);
            }
        }

        foreach ($frontmatter as $key => $value) {
            if (! str_starts_with($key, 'customProperty.')) {
                continue;
            }

            $propKey = substr($key, \strlen('customProperty.'));
            $page->setCustomProperty($propKey, $value);
            $page->registerManagedPropertyKey($propKey);
        }

        // Top-level keys backed by a flat converter (e.g. mainImageFormat) are
        // managed custom properties in the on-disk frontmatter shape. Route them
        // through the converter so the API accepts the same payload as the sync.
        if (null !== $this->converterRegistry) {
            foreach ($frontmatter as $key => $value) {
                if (! $this->converterRegistry->hasConverter($key)) {
                    continue;
                }

                $converted = $this->converterRegistry->fromFlatValue($key, $value);
                if (null === $converted) {
                    // A non-null input the converter cannot resolve (e.g. an
                    // unknown mainImageFormat label) is an invalid value, not an
                    // unset one — reject it so the client gets a 422 instead of
                    // a stored string that crashes the int-typed render.
                    if (null !== $value) {
                        throw new InvalidFrontmatterException($key, $value);
                    }

                    continue;
                }

                $page->setCustomProperty($key, $converted);
                $page->registerManagedPropertyKey($key);
            }
        }

        // Any remaining top-level key that is neither a recognized column nor a
        // converter-backed managed property is a bare custom property in the
        // on-disk frontmatter shape: the flat exporter unpacks customProperties
        // to the top level (e.g. searchExcerpt, targetKeyword, toc), so a payload
        // built from a .md snapshot arrives with those keys flattened. Mirror the
        // flat-file importer and route them into customProperties instead of
        // dropping them, so the snapshot round-trips through the API without
        // silent data loss. property_exists() keeps real declared columns (e.g.
        // extendedPage, holdPublicationAt) out of customProperties.
        foreach ($frontmatter as $key => $value) {
            if (\in_array($key, self::RESERVED_FRONTMATTER_KEYS, true)) {
                continue;
            }

            if (str_starts_with($key, 'customProperty.')) {
                continue;
            }

            if (property_exists($page, $key)) {
                continue;
            }

            if (null !== $this->converterRegistry && $this->converterRegistry->hasConverter($key)) {
                continue;
            }

            $page->setCustomProperty($key, $value);
            $page->registerManagedPropertyKey($key);
        }
    }

    private function parseDateTime(mixed $value): ?DateTimeInterface
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (! \is_string($value) && ! is_numeric($value)) {
            return null;
        }

        try {
            // Reuse the flat sync's parser so the API accepts the same date
            // formats and `draft` sentinel as the on-disk frontmatter.
            $parsed = PublishedAtConverter::fromFlatValue($value);
        } catch (Exception) {
            return null;
        }

        // Page::$publishedAt is mapped DATETIME_MUTABLE; Doctrine rejects a
        // DateTimeImmutable at flush, so normalize to a mutable DateTime.
        return $parsed instanceof DateTimeImmutable ? DateTime::createFromInterface($parsed) : $parsed;
    }

    private function resolveMedia(mixed $reference): ?Media
    {
        if (! \is_string($reference) || '' === $reference) {
            return null;
        }

        return $this->mediaRepository->findOneByFileNameOrHistory($reference);
    }

    private function resolvePageRef(mixed $reference, string $host): ?Page
    {
        if (! \is_string($reference) || '' === $reference) {
            return null;
        }

        return $this->pageRepository->findOneBy(['slug' => $reference, 'host' => $host]);
    }

    /**
     * Link the page to its hreflang siblings. The payload is the whole list, so a
     * ref it drops is unlinked and `translations: []` clears the group;
     * Page::addTranslation() keeps the relation symmetric, so only one side of a
     * pair needs to be sent. Everything is resolved before anything is mutated: an
     * unknown ref leaves the existing group untouched instead of half-applying it.
     */
    private function syncTranslations(Page $page, mixed $references): void
    {
        if (! \is_array($references)) {
            throw new InvalidFrontmatterException('translations', $references, 'Expected a list of page references.');
        }

        $targets = [];
        foreach ($references as $reference) {
            if (! \is_string($reference) || '' === $reference) {
                throw new InvalidFrontmatterException('translations', $reference, 'Expected a page reference ("slug" or "host/slug").');
            }

            $translation = $this->resolveTranslationRef($reference, $page->host);
            if (! $translation instanceof Page) {
                throw new InvalidFrontmatterException('translations', $reference, 'Unknown page: create it before linking it as a translation.');
            }

            $targets[] = $translation;
        }

        foreach ($page->getTranslations()->toArray() as $current) {
            if (! \in_array($current, $targets, true)) {
                $page->removeTranslation($current);
            }
        }

        foreach ($targets as $translation) {
            $page->addTranslation($translation);
        }
    }

    /**
     * A translation reference is a bare slug on the page's own host, or
     * "host/slug" for the usual cross-locale case (one host per locale) — the
     * same shape the flat frontmatter uses. The part before the first "/" is
     * matched against the registered hosts to tell it from a nested slug.
     */
    private function resolveTranslationRef(string $reference, string $host): ?Page
    {
        $slashPosition = strpos($reference, '/');
        if (false !== $slashPosition) {
            $referencedHost = substr($reference, 0, $slashPosition);
            if ($this->siteRegistry->isKnownHost($referencedHost)) {
                return $this->pageRepository->findOneBy([
                    'slug' => substr($reference, $slashPosition + 1),
                    'host' => $referencedHost,
                ]);
            }
        }

        return $this->resolvePageRef($reference, $host);
    }

    /**
     * Cross-host siblings (the multi-locale norm) need their host in the ref, or
     * the exported list cannot be sent back as-is.
     */
    private function buildTranslationRef(Page $translation, string $host): string
    {
        return $translation->host !== $host
            ? $translation->host.'/'.$translation->getSlug()
            : $translation->getSlug();
    }

    /**
     * Build a transient Page (not persisted) for `/api/page/preview`.
     *
     * @param array<string, mixed> $frontmatter
     */
    public function buildTransient(string $host, string $slug, array $frontmatter, ?string $body): Page
    {
        $page = new Page();
        $page->host = $host;
        $page->setSlug($slug);
        $this->applyFrontmatter($page, $frontmatter);
        if (null !== $body) {
            $page->setMainContent($body);
        }

        return $page;
    }

    /**
     * Minimal projection used by /api/page/search.
     *
     * @return array<string, mixed>
     */
    public function summary(Page $page): array
    {
        return [
            'host' => $page->host,
            'slug' => $page->getSlug(),
            'h1' => $page->getH1(),
            'locale' => $page->locale,
            'holdPublication' => $page->isHoldPublication(),
            'updatedAt' => $page->updatedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    public function dumpFrontmatterYaml(Page $page): string
    {
        return Yaml::dump($this->toArray($page)['frontmatter'], 4, 2);
    }
}
