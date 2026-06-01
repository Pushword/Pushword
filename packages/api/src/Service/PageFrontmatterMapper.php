<?php

namespace Pushword\Api\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\Yaml\Yaml;

/**
 * Translates between the flat-file frontmatter shape (the editor-facing dict)
 * and the Page entity columns. The same key set the markdown sync uses, so a
 * payload accepted by the API is also accepted by the on-disk format.
 */
final readonly class PageFrontmatterMapper
{
    /** Scalar fields exposed via proper get/set methods on Page. */
    private const array SETTER_FIELDS = [
        'h1', 'title', 'name', 'metaRobots',
    ];

    /** Scalar fields exposed as public properties on Page. */
    private const array PROPERTY_FIELDS = [
        'host', 'locale', 'template', 'editMessage',
    ];

    public function __construct(
        private PageRepository $pageRepository,
        private MediaRepository $mediaRepository,
    ) {
    }

    /**
     * @return array{frontmatter: array<string, mixed>, body: string}
     */
    public function toArray(Page $page): array
    {
        $frontmatter = [];

        foreach (self::SETTER_FIELDS as $field) {
            $getter = 'get'.ucfirst($field);
            $frontmatter[$field] = $page->$getter();
        }

        foreach (self::PROPERTY_FIELDS as $field) {
            $frontmatter[$field] = $page->$field;
        }

        $frontmatter['slug'] = $page->getSlug();
        $frontmatter['weight'] = $page->getWeight();
        $frontmatter['tags'] = $page->getTagList();
        $frontmatter['publishedAt'] = $page->getPublishedAt()?->format(DateTimeInterface::ATOM);
        $frontmatter['mainImage'] = $page->getMainImage()?->getFileName();
        $frontmatter['parentPage'] = $page->getParentPage()?->getSlug();
        $frontmatter['translations'] = array_values(array_map(
            static fn (Page $t): string => $t->getSlug(),
            $page->getTranslations()->toArray(),
        ));
        $frontmatter['customProperties'] = $page->getCustomProperties();

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
        foreach (self::SETTER_FIELDS as $field) {
            if (! \array_key_exists($field, $frontmatter)) {
                continue;
            }

            $value = $frontmatter[$field];
            if (! \is_string($value) && null !== $value) {
                continue;
            }

            $setter = 'set'.ucfirst($field);
            $page->$setter($value ?? '');
        }

        foreach (self::PROPERTY_FIELDS as $field) {
            if (! \array_key_exists($field, $frontmatter)) {
                continue;
            }

            $value = $frontmatter[$field];
            if (! \is_string($value) && null !== $value) {
                continue;
            }

            $page->$field = $value;
        }

        if (\array_key_exists('slug', $frontmatter) && \is_string($frontmatter['slug']) && '' !== $frontmatter['slug']) {
            $page->setSlug($frontmatter['slug']);
        }

        if (\array_key_exists('weight', $frontmatter) && is_numeric($frontmatter['weight'])) {
            $page->setWeight((int) $frontmatter['weight']);
        }

        if (\array_key_exists('tags', $frontmatter) && \is_array($frontmatter['tags'])) {
            /** @var list<string> $tags */
            $tags = array_values(array_filter($frontmatter['tags'], is_string(...)));
            $page->setTags($tags);
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
            $page->setCustomProperties($frontmatter['customProperties']);
        }

        foreach ($frontmatter as $key => $value) {
            if (! str_starts_with((string) $key, 'customProperty.')) {
                continue;
            }

            $page->setCustomProperty(substr((string) $key, \strlen('customProperty.')), $value);
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
        if (null === $reference || '' === $reference) {
            return null;
        }

        if (! \is_string($reference)) {
            return null;
        }

        return $this->mediaRepository->findOneByFileNameOrHistory($reference);
    }

    private function resolvePageRef(mixed $reference, string $host): ?Page
    {
        if (null === $reference || '' === $reference) {
            return null;
        }

        if (! \is_string($reference)) {
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
            'updatedAt' => $page->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }

    public function dumpFrontmatterYaml(Page $page): string
    {
        return Yaml::dump($this->toArray($page)['frontmatter'], 4, 2);
    }
}
