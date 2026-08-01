<?php

namespace Pushword\Flat\Serializer;

use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\RevisionCalculator;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Utils\Entity;
use Pushword\Flat\Converter\PropertyConverterRegistry;
use Pushword\Flat\Converter\PublishedAtConverter;
use Pushword\Flat\Exporter\ExporterDefaultValueHelper;
use Spatie\YamlFrontMatter\Document;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Stringable;
use Symfony\Component\Yaml\Yaml;

/**
 * Single owner of the flat page-file format: the canonical `.md` text for a
 * Page (front matter, `revision:` stamp, body) and the reverse parse. Every
 * writer (flat export, API markdown responses) and reader (flat import, API
 * markdown intake) goes through here so the format cannot fork again.
 */
final class PageFileSerializer
{
    private readonly ExporterDefaultValueHelper $defaultValue;

    /** @var string[]|null Cached entity properties (same for all Page instances) */
    private ?array $cachedEntityProperties = null;

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly PropertyConverterRegistry $converterRegistry,
        private readonly RevisionCalculator $revisions,
    ) {
        $this->defaultValue = new ExporterDefaultValueHelper();
    }

    /**
     * Canonical file text a flat export writes for this page.
     */
    public function serialize(Page $page): string
    {
        $baseProperties = ['title', 'h1', 'slug'];
        $entityProperties = $this->cachedEntityProperties ??= array_filter(
            Entity::getProperties($page),
            static fn (string $prop): bool => 'id' !== $prop,
        );

        $properties = array_unique([...$baseProperties, ...$entityProperties]);

        $data = [];
        foreach ($properties as $property) {
            if ('customProperties' === $property) {
                continue; // Will be unpacked separately
            }

            $value = $this->getValue($property, $page);
            if (null === $value) {
                continue;
            }

            $data[$property] = $value;
        }

        // Internal redirects authored on this page (Jekyll redirect_from style).
        // Emitted as a {path: code} map; ksort keeps the output stable for idempotency.
        $redirectFrom = $page->redirectFrom;
        if ([] !== $redirectFrom) {
            ksort($redirectFrom);
            $data['redirectFrom'] = $redirectFrom;
        }

        // Unpack custom properties at top level and apply converters
        foreach ($page->customProperties as $key => $value) {
            $converted = $this->converterRegistry->toFlatValue($key, $value);
            if (null !== $converted) {
                $data[$key] = $converted;
            }
        }

        $metaData = Yaml::dump($this->normalizeQuotesDeep($data), indent: 2);

        // Stamp the canonical revision id (content hash) last in the front matter.
        // Matches the API's ETag / If-Match value so agents can read it from the
        // .md and PUT back without a preliminary GET. The `# read only` comment
        // tells editors not to touch it; the value is ignored on parse-and-apply
        // paths (see PageImporter::editPage and the API markdown intake).
        $metaData .= 'revision: '.$this->revisions->compute($page).' # read only'.\PHP_EOL;

        // Normalize typographic quotes in the body only. The front matter values
        // were already normalized *before* Yaml::dump (see normalizeQuotesDeep)
        // so the dumper could escape them correctly — running normalizeQuotes over
        // the dumped YAML would un-escape apostrophes inside single-quoted scalars
        // (e.g. 'l''Albanie' → 'l'Albanie') and produce invalid YAML.
        return '---'.\PHP_EOL.$metaData.'---'.\PHP_EOL.\PHP_EOL.$this->normalizeQuotes($page->getMainContent());
    }

    /**
     * Split a page file into front matter and body, byte-preserving the body.
     *
     * The line-anchored parser (not YamlFrontMatter::parse) is deliberate: the
     * plain parser splits on every `---` line in the document and re-joins with
     * fixed padding, so a tight rule in the body (`A\n---\nB`, a setext heading
     * in markdown) came back as `A\n\n---\n\nB` — silent corruption. The
     * complex parser only takes the first two `---` lines as delimiters, which
     * is only safe when the document actually opens with front matter — hence
     * the starts-with gate; without it two body rules would be misread as a
     * front-matter block.
     */
    public function parse(string $content): Document
    {
        // Strip UTF-8 BOM if present
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! str_starts_with($content, '---')) {
            return new Document([], $content);
        }

        return YamlFrontMatter::markdownCompatibleParse($content);
    }

    private function normalizeQuotes(string $text): string
    {
        return strtr($text, [
            "\u{2018}" => "'", // left single quote
            "\u{2019}" => "'", // right single quote / apostrophe
            "\u{201C}" => '"', // left double quote
            "\u{201D}" => '"', // right double quote
        ]);
    }

    /**
     * Straighten typographic quotes in every string value before the YAML dump,
     * so Yaml::dump() escapes the resulting apostrophes correctly. Applied
     * recursively to support nested front-matter arrays (e.g. redirectFrom).
     *
     * @param array<int|string, mixed> $data
     *
     * @return array<int|string, mixed>
     */
    private function normalizeQuotesDeep(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($value)) {
                $data[$key] = $this->normalizeQuotes($value);
            } elseif (\is_array($value)) {
                $data[$key] = $this->normalizeQuotesDeep($value);
            }
        }

        return $data;
    }

    /**
     * @return scalar|string[]|null
     */
    private function getValue(string $property, Page $page): mixed
    {
        if ('mainContent' === $property) {
            return null;
        }

        if ('mainImage' === $property) {
            return null !== $page->mainImage ? (string) $page->mainImage : null;
        }

        if ('template' === $property) {
            return $page->template;
        }

        $getter = 'get'.ucfirst($property);
        $value = method_exists($page, $getter) ? $page->$getter() : $page->{$property}; // @phpstan-ignore-line

        if ('publishedAt' === $property) {
            assert(null === $value || $value instanceof DateTimeInterface);

            return PublishedAtConverter::toFlatValue($value);
        }

        if ($value instanceof Page) {
            $value = $value->getSlug();
        }

        if ($value instanceof Collection) {
            if ($value->isEmpty()) {
                return null;
            }

            $currentHost = $this->apps->get($page->host)->getMainHost();

            if ('translations' === $property) {
                $siteLocale = $this->apps->get($page->host)->locale;
                $isMainLocale = '' === $page->locale || $page->locale === $siteLocale;

                if (! $isMainLocale) {
                    $mainLocalePages = $value->filter(
                        static fn (mixed $t): bool => $t instanceof Page
                            && $t->host === $currentHost
                            && ('' === $t->locale || $t->locale === $siteLocale)
                    );
                    if (! $mainLocalePages->isEmpty()) {
                        $value = $mainLocalePages;
                    }
                }
            }

            $slugs = [];
            foreach ($value as $item) {
                if ($item instanceof Page) {
                    $slugs[] = $item->host !== $currentHost
                        ? $item->host.'/'.$item->getSlug()
                        : $item->getSlug();
                }
            }

            return [] === $slugs ? null : $slugs;
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (null === $value) {
            return null;
        }

        if ('tags' === $property && in_array($value, [null, [], ''], true)) {
            return null;
        }

        if ($value === $this->defaultValue->get($property)) {
            return null;
        }

        if (in_array($property, ['createdAt', 'updatedAt', 'host', 'slug'], true)) {
            return null;
        }

        if ('locale' === $property && $value === $this->apps->get($page->host)->locale) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i');
        }

        if (! is_scalar($value)) {
            return null;
        }

        return $value;
    }
}
