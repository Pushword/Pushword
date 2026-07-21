<?php

namespace Pushword\Repurpose\Service;

use Pushword\Repurpose\Model\LaidOutText;
use Pushword\Repurpose\Model\TextLine;

/**
 * Lays out a text block into a fixed frame, entirely in PHP, so overflow becomes
 * a validation error before anything is rendered.
 *
 * Two things the naïve version gets wrong, both handled here:
 *
 * - **Units.** GD's `imagettfbbox` sizes in *points*; SVG/CSS `font-size` is in
 *   *pixels* (72/96). Measuring at the raw px size overstates every width by ~33%,
 *   so we always measure at `cssPx × {@see self::PT_PER_PX}`. The returned width is
 *   then in the same pixel space as the SVG frame.
 * - **Binding.** Each line records the pixel width measured for it. The renderer
 *   emits that as `textLength` with `lengthAdjust="spacingAndGlyphs"`, so the
 *   browser renders the line at exactly this width — layout is contractual, not
 *   "hopefully close".
 *
 * The wrap is a plain greedy word wrap measured with the same primitive Imagine's
 * `wrapText()` uses; we compute it directly (rather than reusing `wrapText()`)
 * because we need each final line's pixel width for `textLength`, which `wrapText()`
 * does not return.
 */
final class TextLayout
{
    /** points per CSS pixel: 72/96. */
    public const float PT_PER_PX = 0.75;

    /**
     * Shrink-to-fit: wrap at the start size, and if the block is taller than the
     * frame, step the size down until it fits or reaches the minimum. If it still
     * overflows at the minimum size, lay it out anyway and flag it.
     */
    public function layout(
        string $text,
        string $fontFile,
        float $maxWidthPx,
        float $maxHeightPx,
        float $startSizePx = 96.0,
        float $minSizePx = 24.0,
        float $lineHeightFactor = 1.2,
    ): LaidOutText {
        $text = trim($text);
        if ('' === $text) {
            return new LaidOutText([], $startSizePx, $startSizePx * $lineHeightFactor, false);
        }

        for ($size = $startSizePx; $size >= $minSizePx; --$size) {
            $lines = $this->wrap($text, $fontFile, $size, $maxWidthPx);
            $lineHeight = $size * $lineHeightFactor;
            $fitsHeight = \count($lines) * $lineHeight <= $maxHeightPx;
            $fitsWidth = $this->widest($lines) <= $maxWidthPx + 0.5;

            if ($fitsHeight && $fitsWidth) {
                return new LaidOutText($lines, $size, $lineHeight, false);
            }
        }

        $lines = $this->wrap($text, $fontFile, $minSizePx, $maxWidthPx);

        return new LaidOutText($lines, $minSizePx, $minSizePx * $lineHeightFactor, true);
    }

    /**
     * Greedy word wrap at a fixed size, honouring explicit newlines. Each returned
     * line carries its measured pixel width.
     *
     * @return TextLine[]
     */
    public function wrap(string $text, string $fontFile, float $cssPx, float $maxWidthPx): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [$text] as $paragraph) {
            $this->wrapParagraph($paragraph, $fontFile, $cssPx, $maxWidthPx, $lines);
        }

        return $lines;
    }

    /**
     * @param TextLine[] $lines
     */
    private function wrapParagraph(string $paragraph, string $fontFile, float $cssPx, float $maxWidthPx, array &$lines): void
    {
        $words = preg_split('/\s+/', trim($paragraph)) ?: [];
        $words = array_values(array_filter($words, static fn (string $w): bool => '' !== $w));
        if ([] === $words) {
            return;
        }

        $current = array_shift($words);
        foreach ($words as $word) {
            $candidate = $current.' '.$word;
            if ($this->measureWidth($candidate, $fontFile, $cssPx) > $maxWidthPx) {
                $lines[] = new TextLine($current, $this->measureWidth($current, $fontFile, $cssPx));
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        $lines[] = new TextLine($current, $this->measureWidth($current, $fontFile, $cssPx));
    }

    /**
     * Width in CSS pixels of a string at a given CSS font size, measured through
     * FreeType with the point→pixel correction applied.
     */
    public function measureWidth(string $text, string $fontFile, float $cssPx): float
    {
        if ('' === $text) {
            return 0.0;
        }

        $box = imagettfbbox($cssPx * self::PT_PER_PX, 0, $fontFile, $text);
        if (false === $box) {
            return 0.0;
        }

        $right = $box[2] ?? 0;
        $left = $box[0] ?? 0;
        if (! \is_int($right) || ! \is_int($left)) {
            return 0.0;
        }

        return abs($right - $left);
    }

    /**
     * @param TextLine[] $lines
     */
    private function widest(array $lines): float
    {
        $max = 0.0;
        foreach ($lines as $line) {
            $max = max($max, $line->width);
        }

        return $max;
    }
}
