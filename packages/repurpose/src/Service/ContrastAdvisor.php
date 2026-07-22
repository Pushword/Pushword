<?php

namespace Pushword\Repurpose\Service;

use Pushword\Repurpose\Model\Carousel;
use Pushword\Repurpose\Model\Palette;
use Pushword\Repurpose\Model\Slide;

/**
 * Flags slides whose text is likely unreadable — dark text over a photo, tone-on-
 * tone palettes. These are **warnings, never violations**: "valid but illegible"
 * is the worst failure mode for an agent-driven tool, but legibility is a
 * judgement call the validator must not hard-fail (a deliberate low-contrast
 * design stays saveable).
 *
 * The effective background is the flat palette colour, or for an image slide the
 * image's mean luminance (measured from the embedded derivative through GD)
 * darkened by the overlay — an approximation of what sits behind the text, which
 * is exactly the trap: `palette.text` is applied verbatim by the renderer.
 * Threshold is WCAG AA for large text (3:1) since slide copy is large by design.
 */
final readonly class ContrastAdvisor
{
    private const float MIN_RATIO = 3.0;

    public function __construct(
        private MediaResolver $media,
    ) {
    }

    /**
     * @return list<array{path: string, message: string}>
     */
    public function warnings(Carousel $carousel): array
    {
        $warnings = [];
        foreach ($carousel->slides as $index => $slide) {
            if (null === $slide->tagline && null === $slide->title && null === $slide->paragraph) {
                continue;
            }

            $text = $this->color($carousel, $slide, static fn (Palette $p): ?string => $p->text) ?? SlideRenderer::DEFAULT_TEXT;
            $textLum = $this->hexLuminance($text);
            if (null === $textLum) {
                continue;
            }

            [$bgLum, $bgLabel] = $this->effectiveBackground($carousel, $slide);
            if (null === $bgLum) {
                continue;
            }

            $ratio = $this->ratio($textLum, $bgLum);
            if ($ratio >= self::MIN_RATIO) {
                continue;
            }

            // Advise the direction with the most headroom: against a background
            // below ~0.18 luminance, white text can reach a higher ratio than
            // black, and vice versa ((bg+0.05)² = 0.0525 is the crossover).
            $suggestLighter = $bgLum < 0.179;
            $warnings[] = [
                'path' => \sprintf('slides[%d]', $index),
                'message' => \sprintf(
                    'Text %s over %s: estimated contrast %.1f:1, below %.0f:1 (WCAG AA large text) — likely unreadable. Use a %s text colour%s.',
                    $text,
                    $bgLabel,
                    $ratio,
                    self::MIN_RATIO,
                    $suggestLighter ? 'lighter' : 'darker',
                    $slide->hasImage() && $suggestLighter ? ', or raise the overlay' : '',
                ),
            ];
        }

        return $warnings;
    }

    /**
     * @return array{0: float|null, 1: string} [luminance, human label]
     */
    private function effectiveBackground(Carousel $carousel, Slide $slide): array
    {
        // Sample the primary image (the whole frame, or a split's first cell); the
        // text is bound to the deck margins, which fall over that cell.
        $image = $slide->firstImage();
        if (null === $image) {
            $bg = $this->color($carousel, $slide, static fn (Palette $p): ?string => $p->bg) ?? SlideRenderer::DEFAULT_BG;

            return [$this->hexLuminance($bg), \sprintf('background %s', $bg)];
        }

        $path = $this->media->derivativePath($image->media, 'md');
        $imageLum = null === $path ? null : $this->meanLuminance($path);
        if (null === $imageLum) {
            // Missing media renders as the flat placeholder, without overlay.
            return [$this->hexLuminance(SlideRenderer::MISSING_MEDIA_BG), 'the missing-media placeholder'];
        }

        // The overlay is black at `overlay` opacity, composited over the image.
        return [
            $imageLum * (1.0 - $slide->overlay),
            \sprintf('the image "%s" (overlay %s)', $image->media, round($slide->overlay, 2)),
        ];
    }

    /**
     * @param callable(Palette): ?string $pick
     */
    private function color(Carousel $carousel, Slide $slide, callable $pick): ?string
    {
        foreach ([$slide->palette, $carousel->palette] as $palette) {
            if ($palette instanceof Palette) {
                $color = $pick($palette);
                if (null !== $color) {
                    return $color;
                }
            }
        }

        return null;
    }

    /**
     * WCAG relative luminance of a `#rgb`/`#rrggbb` colour, or null when malformed.
     */
    private function hexLuminance(string $hex): ?float
    {
        $hex = ltrim($hex, '#');
        if (3 === \strlen($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (1 !== preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return null;
        }

        $channel = static function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(substr($hex, 0, 2))
            + 0.7152 * $channel(substr($hex, 2, 2))
            + 0.0722 * $channel(substr($hex, 4, 2));
    }

    /**
     * Mean relative luminance of an image file, sampled on a 16×16 thumbnail.
     * Null when GD cannot decode it (unknown format, corrupt file).
     */
    private function meanLuminance(string $path): ?float
    {
        $bytes = @file_get_contents($path);
        if (false === $bytes || '' === $bytes) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);
        if (false === $image) {
            return null;
        }

        $thumb = imagescale($image, 16, 16);
        if (false === $thumb) {
            return null;
        }

        $sum = 0.0;
        for ($x = 0; $x < 16; ++$x) {
            for ($y = 0; $y < 16; ++$y) {
                $rgb = imagecolorat($thumb, $x, $y);
                $lum = $this->hexLuminance(\sprintf('#%06x', $rgb & 0xFFFFFF));
                $sum += $lum ?? 0.0;
            }
        }

        return $sum / 256;
    }

    private function ratio(float $a, float $b): float
    {
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }
}
