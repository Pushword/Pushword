<?php

namespace Pushword\Repurpose\Service;

/**
 * Per-network publishing rules, split into two kinds that must never be confused:
 *
 * - `limits` are **platform-enforced** — a spec that breaks one is invalid, so
 *   the validator raises an error.
 * - `guidance` is **engagement advice** — the agent may read it, but the
 *   validator never raises it, otherwise it would emit opinions as errors.
 *
 * `formats` lists the {@see FormatRegistry} ids allowed for the network; `export`
 * says whether slides ship as loose images or a single document (PDF);
 * `feedMobile` is the typical CSS-pixel width a slide renders at in the network's
 * mobile feed — the realistic worst case for judging text size (approximate,
 * editable data: platforms drift). Pinterest is its two-column mobile grid.
 */
final class NetworkRegistry
{
    /**
     * @var array<string, array{
     *     formats: list<string>,
     *     export: string,
     *     feedMobile: int,
     *     limits: array{maxSlides?: int, maxPages?: int, maxFileMb?: int, samePageSize?: bool, caption?: int},
     *     guidance: list<string>
     * }>
     */
    public const array NETWORKS = [
        'linkedin' => [
            'formats' => ['linkedin-4-5', 'linkedin-1-1'],
            'export' => 'pdf',
            'feedMobile' => 390,
            'limits' => ['maxPages' => 300, 'maxFileMb' => 100, 'samePageSize' => true, 'caption' => 3000],
            'guidance' => ['Ideally under 10 pages — shorter, tightly paced carousels outperform.'],
        ],
        'instagram' => [
            'formats' => ['instagram-4-5', 'instagram-3-4', 'instagram-1-1', 'story-9-16'],
            'export' => 'images',
            'feedMobile' => 390,
            'limits' => ['maxSlides' => 20, 'caption' => 2200],
            'guidance' => ['The first slide locks the aspect ratio for the rest (unless you opt into the mixed-ratio carousel).'],
        ],
        'facebook' => [
            'formats' => ['facebook-1-1'],
            'export' => 'images',
            'feedMobile' => 390,
            'limits' => ['maxSlides' => 10, 'caption' => 63206],
            'guidance' => [],
        ],
        'pinterest' => [
            'formats' => ['pinterest-2-3'],
            'export' => 'images',
            'feedMobile' => 186,
            'limits' => ['maxSlides' => 5, 'caption' => 500],
            'guidance' => ['Pinterest carousels allow 2–5 images.', 'Pins are browsed in a ~186px two-column mobile grid — oversize the text.'],
        ],
        'threads' => [
            'formats' => ['threads-4-5'],
            'export' => 'images',
            'feedMobile' => 390,
            'limits' => ['maxSlides' => 20, 'caption' => 500],
            'guidance' => [],
        ],
        'deck' => [
            'formats' => ['deck-16-9'],
            'export' => 'pdf',
            'feedMobile' => 390,
            'limits' => [],
            'guidance' => [],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::NETWORKS);
    }

    /**
     * The format ids allowed for a network (empty for an unknown network).
     *
     * @return list<string>
     */
    public static function formatsFor(string $network): array
    {
        return self::NETWORKS[$network]['formats'] ?? [];
    }

    /**
     * Typical mobile-feed rendering width in CSS px — what previews calibrate to.
     */
    public function mobileWidth(string $network): int
    {
        return self::NETWORKS[$network]['feedMobile'] ?? 390;
    }

    /**
     * @return array{formats: list<string>, export: string, feedMobile: int, limits: array<string, int|bool>, guidance: list<string>}|null
     */
    public function get(string $network): ?array
    {
        return self::NETWORKS[$network] ?? null;
    }

    /**
     * @return array<string, array{formats: list<string>, export: string, feedMobile: int, limits: array<string, int|bool>, guidance: list<string>}>
     */
    public function all(): array
    {
        return self::NETWORKS;
    }
}
