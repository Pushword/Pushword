<?php

namespace Pushword\Repurpose\Model;

/**
 * Where a source image sits inside a slide frame: the `<image>` rect in frame
 * coordinates (may extend past the frame — the SVG clips it), the cache
 * derivative to embed, and any quality issue the validator should report.
 */
final readonly class ImagePlacement
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        /** Filter-set name of the cached derivative to embed (e.g. "lg"). */
        public string $filter,
        /** True when the source cannot fill the frame at the requested zoom (would upscale). */
        public bool $tooSmall,
    ) {
    }
}
