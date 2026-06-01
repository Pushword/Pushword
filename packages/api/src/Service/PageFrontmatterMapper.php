<?php

namespace Pushword\Api\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Flat\Converter\PropertyConverterRegistry;
use Symfony\Component\Yaml\Yaml;

/**
 * Translates between the flat-file frontmatter shape (the editor-facing dict)
 * and the Page entity columns. The same key set the markdown sync uses, so a
 * payload accepted by the API is also accepted by the on-disk format.
 */
final readonly class PageFrontmatterMapper
{
    public function __construct(
        private PageRepository $pageRepository,
        private MediaRepository $mediaRepository,
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
            'mainImage' => $page->getMainImage()?->getFileName(),
            'parentPage' => $page->getParentPage()?->getSlug(),
            'translations' => array_values(array_map(
                static fn (Page $t): string => $t->getSlug(),
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

        if (\array_key_exists('mainImage', $frontmatter)) {
            $page->setMainImage($this->resolveMedia($frontmatter['mainImage']));
        }

        if (\array_key_exists('parentPage', $frontmatter)) {
            $page->setParentPage($this->resolvePageRef($frontmatter['parentPage'], $page->host));
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
                if (null !== $converted) {
                    $page->setCustomProperty($key, $converted);
                    $page->registerManagedPropertyKey($key);
                }
            }
        }
    }

    private function parseDateTime(mixed $value): ?DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (! \is_string($value) && ! is_numeric($value)) {
            return null;
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (Exception) {
            return null;
        }
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
            'updatedAt' => $page->updatedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    public function dumpFrontmatterYaml(Page $page): string
    {
        return Yaml::dump($this->toArray($page)['frontmatter'], 4, 2);
    }
}
