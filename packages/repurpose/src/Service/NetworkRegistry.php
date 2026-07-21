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
 * says whether slides ship as loose images or a single document (PDF).
 */
final class NetworkRegistry
{
    /**
     * @var array<string, array{
     *     formats: list<string>,
     *     export: string,
     *     limits: array{maxSlides?: int, maxPages?: int, maxFileMb?: int, samePageSize?: bool, caption?: int},
     *     guidance: list<string>
     * }>
     */
    public const array NETWORKS = [
        'linkedin' => [
            'formats' => ['linkedin-4-5', 'linkedin-1-1'],
            'export' => 'pdf',
            'limits' => ['maxPages' => 300, 'maxFileMb' => 100, 'samePageSize' => true, 'caption' => 3000],
            'guidance' => ['Ideally under 10 pages — shorter, tightly paced carousels outperform.'],
        ],
        'instagram' => [
            'formats' => ['instagram-4-5', 'instagram-3-4', 'instagram-1-1', 'story-9-16'],
            'export' => 'images',
            'limits' => ['maxSlides' => 20, 'caption' => 2200],
            'guidance' => ['The first slide locks the aspect ratio for the rest (unless you opt into the mixed-ratio carousel).'],
        ],
        'facebook' => [
            'formats' => ['facebook-1-1'],
            'export' => 'images',
            'limits' => ['maxSlides' => 10, 'caption' => 63206],
            'guidance' => [],
        ],
        'pinterest' => [
            'formats' => ['pinterest-2-3'],
            'export' => 'images',
            'limits' => ['maxSlides' => 5, 'caption' => 500],
            'guidance' => ['Pinterest carousels allow 2–5 images.'],
        ],
        'threads' => [
            'formats' => ['threads-4-5'],
            'export' => 'images',
            'limits' => ['maxSlides' => 20, 'caption' => 500],
            'guidance' => [],
        ],
        'deck' => [
            'formats' => ['deck-16-9'],
            'export' => 'pdf',
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
     * @return array{formats: list<string>, export: string, limits: array<string, int|bool>, guidance: list<string>}|null
     */
    public function get(string $network): ?array
    {
        return self::NETWORKS[$network] ?? null;
    }

    /**
     * @return array<string, array{formats: list<string>, export: string, limits: array<string, int|bool>, guidance: list<string>}>
     */
    public function all(): array
    {
        return self::NETWORKS;
    }
}
