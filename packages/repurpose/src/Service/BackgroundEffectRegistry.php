<?php

namespace Pushword\Repurpose\Service;

/**
 * Deck-wide background effects. Each is authored once on a virtual canvas
 * `slideWidth × slideCount` wide and sliced per slide, so a shape cut by one
 * slide's edge resumes on the next — what makes a swiped carousel feel continuous.
 *
 * Effects are painted in flat white at low opacity so they tint themselves against
 * any palette (no per-palette artwork). Three kinds, keyed by `type`: `pattern`
 * (a seamless tile from {@see self::PATTERNS}), `doodle` (hand-drawn marker
 * scrawls) and `filter` (an SVG filter, e.g. paper grain). Adding a pattern effect
 * is one row here plus one in {@see self::PATTERNS}.
 */
final class BackgroundEffectRegistry
{
    /**
     * @var array<string, array{label: string, category: string, type: string}>
     */
    public const array EFFECTS = [
        'none' => ['label' => 'None', 'category' => 'Basic', 'type' => 'none'],
        'dots' => ['label' => 'Dots', 'category' => 'Dots & Lines', 'type' => 'pattern'],
        'rings' => ['label' => 'Rings', 'category' => 'Dots & Lines', 'type' => 'pattern'],
        'waves' => ['label' => 'Waves', 'category' => 'Dots & Lines', 'type' => 'pattern'],
        'scales' => ['label' => 'Scales', 'category' => 'Dots & Lines', 'type' => 'pattern'],
        'chevron' => ['label' => 'Chevron', 'category' => 'Geometric', 'type' => 'pattern'],
        'hexagon' => ['label' => 'Honeycomb', 'category' => 'Geometric', 'type' => 'pattern'],
        'triangles' => ['label' => 'Triangles', 'category' => 'Geometric', 'type' => 'pattern'],
        'plus' => ['label' => 'Plus', 'category' => 'Geometric', 'type' => 'pattern'],
        'diamonds' => ['label' => 'Diamonds', 'category' => 'Geometric', 'type' => 'pattern'],
        'memphis' => ['label' => 'Memphis', 'category' => 'Playful', 'type' => 'pattern'],
        'sketchy' => ['label' => 'Casual Doodles', 'category' => 'Playful', 'type' => 'doodle'],
        'paper' => ['label' => 'Paper Grain', 'category' => 'Textures', 'type' => 'filter'],
    ];

    /**
     * Tile data for the `pattern`-type effects, sourced from Pattern Monster
     * (MIT — https://github.com/catchspider2002/svelte-svg-patterns). Each is a
     * seamless single-colour tile: `mode` (`fill` / `stroke` / `stroke-join`) is
     * how the palette-tinted white ink is applied, `tile` is the source tile box,
     * `scale` is the on-screen tile width as a fraction of the slide width (so a
     * pattern reads at the same density on a thumbnail and a full slide), `stroke`
     * is the source stroke width and `paths` the raw `<path>` fragments.
     *
     * @var array<string, array{mode: string, tile: array{0: float, 1: float}, scale: float, stroke: float, opacity: float, paths: list<string>}>
     */
    public const array PATTERNS = [
        'dots' => ['mode' => 'fill', 'tile' => [40.0, 40.0], 'scale' => 0.075, 'stroke' => 0.0, 'opacity' => 0.12, 'paths' => ['<path d="M40 45a5 5 0 110-10 5 5 0 010 10zM0 45a5 5 0 110-10 5 5 0 010 10zM0 5A5 5 0 110-5 5 5 0 010 5zm40 0a5 5 0 110-10 5 5 0 010 10z"/>', '<path d="M20 25a5 5 0 110-10 5 5 0 010 10z"/>']],
        'rings' => ['mode' => 'stroke-join', 'tile' => [48.0, 48.0], 'scale' => 0.12, 'stroke' => 2.0, 'opacity' => 0.14, 'paths' => ['<path d="M5.323 7.811a10.233 10.233 0 01-11.77 0m60.894 0a10.234 10.234 0 01-11.77 0M-6.447 40.19a10.234 10.234 0 0111.77 0m37.354 0a10.235 10.235 0 0111.77 0m-24.562-7.817a10.234 10.234 0 01-11.77 0m0-16.746A10.234 10.234 0 0124 13.767c2.107 0 4.162.649 5.886 1.86"/>', '<path d="M15.627 5.323a10.234 10.234 0 010-11.77m16.746 0a10.234 10.234 0 010 11.77M15.627 54.447a10.233 10.233 0 010-11.77m16.746 0a10.234 10.234 0 010 11.77m7.817-24.562a10.234 10.234 0 010-11.77m-32.379 0a10.234 10.234 0 010 11.771"/>']],
        'waves' => ['mode' => 'stroke', 'tile' => [120.0, 80.0], 'scale' => 0.22, 'stroke' => 2.5, 'opacity' => 0.14, 'paths' => ['<path d="M-50.129 12.685C-33.346 12.358-16.786 4.918 0 5c16.787.082 43.213 10 60 10s43.213-9.918 60-10c16.786-.082 33.346 7.358 50.129 7.685"/>', '<path d="M-50.129 32.685C-33.346 32.358-16.786 24.918 0 25c16.787.082 43.213 10 60 10s43.213-9.918 60-10c16.786-.082 33.346 7.358 50.129 7.685"/>', '<path d="M-50.129 52.685C-33.346 52.358-16.786 44.918 0 45c16.787.082 43.213 10 60 10s43.213-9.918 60-10c16.786-.082 33.346 7.358 50.129 7.685"/>', '<path d="M-50.129 72.685C-33.346 72.358-16.786 64.918 0 65c16.787.082 43.213 10 60 10s43.213-9.918 60-10c16.786-.082 33.346 7.358 50.129 7.685"/>']],
        'scales' => ['mode' => 'stroke', 'tile' => [20.0, 20.0], 'scale' => 0.06, 'stroke' => 1.5, 'opacity' => 0.13, 'paths' => ['<path d="M-10-10A10 10 0 00-20 0a10 10 0 0010 10A10 10 0 010 0a10 10 0 00-10-10zM10-10A10 10 0 000 0a10 10 0 0110 10A10 10 0 0120 0a10 10 0 00-10-10zM30-10A10 10 0 0020 0a10 10 0 0110 10A10 10 0 0140 0a10 10 0 00-10-10zM-10 10a10 10 0 00-10 10 10 10 0 0010 10A10 10 0 010 20a10 10 0 00-10-10zM10 10A10 10 0 000 20a10 10 0 0110 10 10 10 0 0110-10 10 10 0 00-10-10zM30 10a10 10 0 00-10 10 10 10 0 0110 10 10 10 0 0110-10 10 10 0 00-10-10z"/>']],
        'chevron' => ['mode' => 'stroke-join', 'tile' => [40.0, 80.0], 'scale' => 0.16, 'stroke' => 2.5, 'opacity' => 0.13, 'paths' => ['<path d="M-10 7.5l20 5 20-5 20 5"/>', '<path d="M-10 27.5l20 5 20-5 20 5"/>', '<path d="M-10 47.5l20 5 20-5 20 5"/>', '<path d="M-10 67.5l20 5 20-5 20 5"/>']],
        'hexagon' => ['mode' => 'stroke', 'tile' => [29.0, 50.115], 'scale' => 0.09, 'stroke' => 2.0, 'opacity' => 0.13, 'paths' => ['<path d="M14.498 16.858L0 8.488.002-8.257l14.5-8.374L29-8.26l-.002 16.745zm0 50.06L0 58.548l.002-16.745 14.5-8.373L29 41.8l-.002 16.744zM28.996 41.8l-14.498-8.37.002-16.744L29 8.312l14.498 8.37-.002 16.745zm-29 0l-14.498-8.37.002-16.744L0 8.312l14.498 8.37-.002 16.745z"/>']],
        'triangles' => ['mode' => 'fill', 'tile' => [40.0, 40.0], 'scale' => 0.07, 'stroke' => 0.0, 'opacity' => 0.1, 'paths' => ['<path d="M0 0l10 20L20 0H0zm10 20l10 20 10-20H10z"/>', '<path d="M20 0l10 20L40 0zm10 20l10 20 10-20zm-40 0L0 40l10-20z"/>']],
        'plus' => ['mode' => 'stroke-join', 'tile' => [32.0, 32.0], 'scale' => 0.06, 'stroke' => 2.0, 'opacity' => 0.13, 'paths' => ['<path d="M40 16h-6m-4 0h-6m8 8v-6m0-4V8M8 16H2m-4 0h-6m8 8v-6m0-4V8"/>', '<path d="M16-8v6m0 4v6m8-8h-6m-4 0H8m8 24v6m0 4v6m8-8h-6m-4 0H8"/>']],
        'diamonds' => ['mode' => 'stroke', 'tile' => [50.0, 50.0], 'scale' => 0.09, 'stroke' => 2.0, 'opacity' => 0.13, 'paths' => ['<path d="M50 25L37.5 50 25 25 37.5 0zm-25 0L12.5 50 0 25 12.5 0z"/>']],
        'memphis' => ['mode' => 'stroke-join', 'tile' => [70.0, 70.0], 'scale' => 0.18, 'stroke' => 2.5, 'opacity' => 0.14, 'paths' => ['<path d="M-4.8 4.44L4 16.59 16.14 7.8M32 30.54l-13.23 7.07 7.06 13.23M-9 38.04l-3.81 14.5 14.5 3.81M65.22 4.44L74 16.59 86.15 7.8M61 38.04l-3.81 14.5 14.5 3.81"/>', '<path d="M59.71 62.88v3h3M4.84 25.54L2.87 27.8l2.26 1.97m7.65 16.4l-2.21-2.03-2.03 2.21m29.26 7.13l.56 2.95 2.95-.55"/>', '<path d="M58.98 27.57l-2.35-10.74-10.75 2.36M31.98-4.87l2.74 10.65 10.65-2.73M31.98 65.13l2.74 10.66 10.65-2.74"/>', '<path d="M8.42 62.57l6.4 2.82 2.82-6.41m33.13-15.24l-4.86-5.03-5.03 4.86m-14-19.64l4.84-5.06-5.06-4.84"/>']],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::EFFECTS);
    }

    /**
     * @return array{label: string, category: string, type: string}|null
     */
    public function get(string $key): ?array
    {
        return self::EFFECTS[$key] ?? null;
    }

    /**
     * The seamless-tile data for a `pattern`-type effect, or null for any other key.
     *
     * @return array{mode: string, tile: array{0: float, 1: float}, scale: float, stroke: float, opacity: float, paths: list<string>}|null
     */
    public function pattern(string $key): ?array
    {
        return self::PATTERNS[$key] ?? null;
    }

    /**
     * @return array<string, array{label: string, category: string, type: string}>
     */
    public function all(): array
    {
        return self::EFFECTS;
    }
}
