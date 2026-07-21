<?php

namespace Pushword\Repurpose\Service;

/**
 * Deck-wide background effects. Each is authored once on a virtual canvas
 * `slideWidth × slideCount` wide and sliced per slide, so a shape cut by one
 * slide's edge resumes on the next — what makes a swiped carousel feel continuous.
 *
 * Effects are painted in flat white/black at low opacity so they tint themselves
 * against any palette (no per-palette artwork). Each is just static SVG path data
 * or one `<pattern>`, so the registry is data — adding an effect is a row plus a
 * fragment (the fragments themselves land with the renderer in P2).
 */
final class BackgroundEffectRegistry
{
    /**
     * @var array<string, array{label: string, primitive: string}>
     */
    public const array EFFECTS = [
        'none' => ['label' => 'None', 'primitive' => 'none'],
        'blobs' => ['label' => 'Blobs', 'primitive' => 'path'],
        'bubbles' => ['label' => 'Bubbles', 'primitive' => 'path'],
        'poly-grid' => ['label' => 'Poly Grid', 'primitive' => 'pattern'],
        'sketchy' => ['label' => 'Sketchy Directions', 'primitive' => 'path'],
        'paper' => ['label' => 'Paper Grain', 'primitive' => 'filter'],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::EFFECTS);
    }

    /**
     * @return array{label: string, primitive: string}|null
     */
    public function get(string $key): ?array
    {
        return self::EFFECTS[$key] ?? null;
    }

    /**
     * @return array<string, array{label: string, primitive: string}>
     */
    public function all(): array
    {
        return self::EFFECTS;
    }
}
