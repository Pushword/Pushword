<?php

namespace Pushword\Repurpose\Service;

/**
 * The output formats a slide can be rendered at, as one editable table.
 *
 * Platform specs drift faster than releases, so every row carries a `source`
 * note and the renderer stays ratio-agnostic (it reads `width`/`height` into the
 * SVG `viewBox`). Adding a format is a data row here — no renderer change.
 *
 * The id list is exposed statically ({@see self::ids()}) so the value-object
 * layer can reference it from an `Assert\Choice(callback: …)` without DI, while
 * the service form ({@see self::get()}, {@see self::all()}) gives the renderer and
 * the API the full per-row data.
 */
final class FormatRegistry
{
    /**
     * @var array<string, array{surface: string, ratio: string, width: int, height: int, star?: bool, source: string}>
     */
    public const array FORMATS = [
        'linkedin-4-5' => ['surface' => 'LinkedIn document', 'ratio' => '4:5', 'width' => 1080, 'height' => 1350, 'star' => true, 'source' => 'community convention; LinkedIn publishes no px spec for document posts'],
        'linkedin-1-1' => ['surface' => 'LinkedIn document', 'ratio' => '1:1', 'width' => 1080, 'height' => 1080, 'source' => 'community convention'],
        'instagram-4-5' => ['surface' => 'Instagram feed', 'ratio' => '4:5', 'width' => 1080, 'height' => 1350, 'star' => true, 'source' => 'Instagram carousel uploader default'],
        'instagram-3-4' => ['surface' => 'Instagram feed (grid-flush)', 'ratio' => '3:4', 'width' => 1080, 'height' => 1440, 'source' => 'matches the 2025 profile grid'],
        'instagram-1-1' => ['surface' => 'Instagram feed', 'ratio' => '1:1', 'width' => 1080, 'height' => 1080, 'source' => 'legacy square'],
        'story-9-16' => ['surface' => 'Instagram Stories / TikTok', 'ratio' => '9:16', 'width' => 1080, 'height' => 1920, 'source' => 'full-screen vertical'],
        'threads-4-5' => ['surface' => 'Threads', 'ratio' => '4:5', 'width' => 1080, 'height' => 1350, 'source' => 'Threads accepts ~any ratio; 4:5 mirrors Instagram'],
        'facebook-1-1' => ['surface' => 'Facebook', 'ratio' => '1:1', 'width' => 1080, 'height' => 1080, 'source' => 'square feed post'],
        'pinterest-2-3' => ['surface' => 'Pinterest', 'ratio' => '2:3', 'width' => 1000, 'height' => 1500, 'source' => 'Pinterest recommended pin ratio (primary-sourced)'],
        'deck-16-9' => ['surface' => 'PowerPoint / deck', 'ratio' => '16:9', 'width' => 1920, 'height' => 1080, 'source' => 'landscape slide'],
    ];

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_keys(self::FORMATS);
    }

    /**
     * @return array{surface: string, ratio: string, width: int, height: int, star?: bool, source: string}|null
     */
    public function get(string $id): ?array
    {
        return self::FORMATS[$id] ?? null;
    }

    /**
     * @return array<string, array{surface: string, ratio: string, width: int, height: int, star?: bool, source: string}>
     */
    public function all(): array
    {
        return self::FORMATS;
    }

    public function width(string $id): int
    {
        return self::FORMATS[$id]['width'] ?? 1080;
    }

    public function height(string $id): int
    {
        return self::FORMATS[$id]['height'] ?? 1350;
    }
}
