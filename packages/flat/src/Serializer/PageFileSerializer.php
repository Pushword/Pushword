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

        $metaData = Yaml::dump($this->normalizeTypographyDeep($data), indent: 2);

        // Stamp the canonical revision id (content hash) last in the front matter.
        // Matches the API's ETag / If-Match value so agents can read it from the
        // .md and PUT back without a preliminary GET. The `# read only` comment
        // tells editors not to touch it; the value is ignored on parse-and-apply
        // paths (see PageImporter::editPage and the API markdown intake).
        $metaData .= 'revision: '.$this->revisions->compute($page).' # read only'.\PHP_EOL;

        // Normalize typography in the body only (straight quotes, plain spaces:
        // typographic characters are re-created at render time by core's
        // Typographer, so they are pure noise in the sources). The front matter
        // values were already normalized *before* Yaml::dump (see
        // normalizeTypographyDeep) so the dumper could escape them correctly —
        // running normalizeTypography over the dumped YAML would un-escape
        // apostrophes inside single-quoted scalars (e.g. 'l''Albanie' →
        // 'l'Albanie') and produce invalid YAML.
        return '---'.\PHP_EOL.$metaData.'---'.\PHP_EOL.\PHP_EOL.$this->normalizeTypography($page->mainContent);
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

    /**
     * Keep in sync with `MarkdownUtils.normalizeTypography()`
     * (admin-block-editor), which applies the same rules on editor saves.
     * Dashes and `×`/`™`/`©` stay: the render does not re-create every
     * author-typed one, so straightening them would be lossy. Code keeps its
     * bytes for the same reason — the render-time Typographer never touches
     * `pre`/`code`, so a straightened `…` in a code sample would render
     * differently forever.
     */
    private function normalizeTypography(string $text): string
    {
        if (! str_contains($text, '`') && ! str_contains($text, '~~~')) {
            return $this->straightenTypography($text);
        }

        $result = '';
        $cursor = 0;
        foreach ($this->codeRanges($text) as [$from, $to]) {
            $result .= $this->straightenTypography(substr($text, $cursor, $from - $cursor));
            $result .= substr($text, $from, $to - $from);
            $cursor = $to;
        }

        return $result.$this->straightenTypography(substr($text, $cursor));
    }

    private function straightenTypography(string $text): string
    {
        return strtr($text, [
            "\u{2018}" => "'", // left single quote
            "\u{2019}" => "'", // right single quote / apostrophe
            "\u{201A}" => "'", // single low quote
            "\u{201C}" => '"', // left double quote
            "\u{201D}" => '"', // right double quote
            "\u{201E}" => '"', // double low quote (German opening)
            "\u{2026}" => '...', // ellipsis
            "\u{202F}" => ' ', // narrow no-break space
            "\u{2009}" => ' ', // thin space
            "\u{00A0}" => ' ', // no-break space
            "\u{00AD}" => '', // soft hyphen
            "\u{200B}" => '', // zero-width space
            "\u{2060}" => '', // word joiner
            "\u{FEFF}" => '', // zero-width no-break space / stray BOM
        ]);
    }

    /**
     * Byte ranges `[from, to)` covered by code: fenced blocks first, then
     * inline code spans in the prose between them. Ordered, non-overlapping.
     *
     * @return list<array{int, int}>
     */
    private function codeRanges(string $text): array
    {
        $ranges = [];
        $cursor = 0;
        foreach ($this->fencedRanges($text) as [$from, $to]) {
            array_push($ranges, ...$this->inlineCodeSpans($text, $cursor, $from));
            $ranges[] = [$from, $to];
            $cursor = $to;
        }

        array_push($ranges, ...$this->inlineCodeSpans($text, $cursor, \strlen($text)));

        return $ranges;
    }

    /**
     * Byte ranges covered by fenced code blocks — the PHP mirror of
     * `MarkdownUtils.fencedRanges()` (admin-block-editor). CommonMark rules:
     * up to three spaces of indent, a closing run of the same character at
     * least as long as the opening one with nothing but space after it, no
     * backtick in a backtick fence's info string, and an unclosed fence runs
     * to the end of the document.
     *
     * @return list<array{int, int}>
     */
    private function fencedRanges(string $text): array
    {
        $ranges = [];
        $open = null; // [fence char, fence length, byte offset]
        $offset = 0;

        foreach (explode("\n", $text) as $line) {
            if (1 === preg_match('/^ {0,3}(`{3,}|~{3,})(.*)$/', $line, $match)) {
                [, $marker, $info] = $match;
                if (null === $open) {
                    if ('`' !== $marker[0] || ! str_contains($info, '`')) {
                        $open = [$marker[0], \strlen($marker), $offset];
                    }
                } elseif ($marker[0] === $open[0] && \strlen($marker) >= $open[1] && '' === trim($info)) {
                    $ranges[] = [$open[2], $offset + \strlen($line)];
                    $open = null;
                }
            }

            $offset += \strlen($line) + 1;
        }

        if (null !== $open) {
            $ranges[] = [$open[2], \strlen($text)];
        }

        return $ranges;
    }

    /**
     * Inline code spans in `$text[$from, $to)`: a backtick run closed by the
     * next run of the same length (CommonMark), never across a blank line.
     *
     * @return list<array{int, int}>
     */
    private function inlineCodeSpans(string $text, int $from, int $to): array
    {
        $segment = substr($text, $from, $to - $from);
        if (! str_contains($segment, '`')) {
            return [];
        }

        preg_match_all('/`+/', $segment, $matches, \PREG_OFFSET_CAPTURE);
        $runs = $matches[0];
        $spans = [];
        $runCount = \count($runs);

        for ($i = 0; $i < $runCount; ++$i) {
            [$run, $runOffset] = $runs[$i];
            $length = \strlen($run);
            for ($j = $i + 1; $j < $runCount; ++$j) {
                [$closer, $closerOffset] = $runs[$j];
                $between = substr($segment, $runOffset + $length, $closerOffset - $runOffset - $length);
                if (1 === preg_match('/\n[ \t]*\n/', $between)) {
                    break; // the paragraph ended: the run is literal
                }

                if (\strlen($closer) === $length) {
                    $spans[] = [$from + $runOffset, $from + $closerOffset + \strlen($closer)];
                    $i = $j; // runs up to the closer belong to the span

                    continue 2;
                }
            }
        }

        return $spans;
    }

    /**
     * Straighten typographic quotes and spaces in every string value before the
     * YAML dump, so Yaml::dump() escapes the resulting apostrophes correctly.
     * Applied recursively to support nested front-matter arrays (e.g.
     * redirectFrom).
     *
     * @param array<int|string, mixed> $data
     *
     * @return array<int|string, mixed>
     */
    private function normalizeTypographyDeep(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($value)) {
                $data[$key] = $this->normalizeTypography($value);
            } elseif (\is_array($value)) {
                $data[$key] = $this->normalizeTypographyDeep($value);
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
            $value = $value->slug;
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
                        ? $item->host.'/'.$item->slug
                        : $item->slug;
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
