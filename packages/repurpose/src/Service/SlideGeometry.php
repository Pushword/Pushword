<?php

namespace Pushword\Repurpose\Service;

use Pushword\Repurpose\Model\ImagePlacement;

/**
 * Turns a focal point + zoom into the `<image>` placement rect for a slide frame,
 * plus which cache derivative to embed and whether the source is too small.
 *
 * The crop is *never* baked into a cached file: a horizontal source filling a
 * vertical frame is just a wide rect with negative `x` that the SVG clips. The
 * same three numbers (focusX, focusY, zoom) therefore stay correct at any output
 * ratio. This class is the single source of the geometry, shared by the renderer
 * and the validator so the two can never disagree.
 */
final class SlideGeometry
{
    /**
     * Cache-derivative widths (mirrors core's default image_filter_sets). The
     * renderer embeds the smallest one that still covers the displayed size.
     *
     * @var array<string, int>
     */
    private const array DERIVATIVES = ['md' => 992, 'lg' => 1200, 'xl' => 1600, 'default' => 1980];

    public function place(
        int $sourceWidth,
        int $sourceHeight,
        float $focusX,
        float $focusY,
        float $zoom,
        int $frameWidth,
        int $frameHeight,
    ): ImagePlacement {
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return new ImagePlacement(0, 0, $frameWidth, $frameHeight, 'default', true);
        }

        // Cover-fit: the smallest scale that fills the frame in both axes, then zoom.
        $cover = max($frameWidth / $sourceWidth, $frameHeight / $sourceHeight);
        $scale = $cover * max(1.0, $zoom);

        $displayWidth = $sourceWidth * $scale;
        $displayHeight = $sourceHeight * $scale;

        // Keep the focal point at the same relative position in frame and image.
        $x = ($frameWidth - $displayWidth) * $focusX;
        $y = ($frameHeight - $displayHeight) * $focusY;

        // Upscaling past the source resolution blurs — flag it (small tolerance).
        $tooSmall = $scale > 1.0001;

        return new ImagePlacement(
            $x,
            $y,
            $displayWidth,
            $displayHeight,
            $this->derivativeFor($displayWidth, $sourceWidth),
            $tooSmall,
        );
    }

    /**
     * Smallest cache derivative whose width covers the displayed width, capped by
     * what the source actually provides (never ask for more pixels than exist).
     */
    private function derivativeFor(float $displayWidth, int $sourceWidth): string
    {
        $needed = min($displayWidth, $sourceWidth);
        foreach (self::DERIVATIVES as $name => $width) {
            if ($width >= $needed) {
                return $name;
            }
        }

        return 'default';
    }
}
