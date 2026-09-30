<?php

declare(strict_types=1);

namespace Pushword\Flat\Serializer;

use DateTimeInterface;
use Spatie\YamlFrontMatter\ComplexMarkdownParser;
use Spatie\YamlFrontMatter\Document;
use Symfony\Component\Yaml\Yaml;

/**
 * The markdown-compatible split, reading the date keys as they were written.
 *
 * YAML turns an unquoted `publishedAt: 2026-09-30` into a Unix timestamp taken in
 * UTC: no date parser reads it back, and whether the author gave an offset is
 * lost. Those keys are read again as dates and handed on as text, so an unquoted
 * date follows the rules of a quoted one. Other keys keep what YAML gives them.
 */
final class FrontMatterParser extends ComplexMarkdownParser
{
    private const array DATE_KEYS = [
        'publishedAt', 'published_at',
        'holdPublicationAt', 'hold_publication_at',
        'createdAt', 'created_at',
        'updatedAt', 'updated_at',
    ];

    public function parse(): Document
    {
        $document = parent::parse();
        /** @var array<mixed> $matter */
        $matter = $document->matter();

        $timestampKeys = array_filter(
            self::DATE_KEYS,
            static fn (string $key): bool => \is_int($matter[$key] ?? null) || \is_float($matter[$key] ?? null),
        );
        if ([] === $timestampKeys) {
            return $document;
        }

        $dates = Yaml::parse($this->getFrontMatter(), Yaml::PARSE_DATETIME);
        foreach ($timestampKeys as $key) {
            $date = \is_array($dates) ? $dates[$key] ?? null : null;
            if (! $date instanceof DateTimeInterface) {
                continue; // a plain number, not a timestamp YAML converted
            }

            // YAML reads a timestamp without offset in UTC: that zone, by name, means none was written.
            $matter[$key] = $date->format('UTC' === $date->getTimezone()->getName() ? 'Y-m-d H:i:s' : 'Y-m-d H:i:sP');
        }

        return new Document($matter, $document->body());
    }
}
