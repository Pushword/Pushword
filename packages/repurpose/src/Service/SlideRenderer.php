<?php

namespace Pushword\Repurpose\Service;

use Pushword\Repurpose\Model\Carousel;
use Pushword\Repurpose\Model\Counter;
use Pushword\Repurpose\Model\Creator;
use Pushword\Repurpose\Model\LaidOutText;
use Pushword\Repurpose\Model\Palette;
use Pushword\Repurpose\Model\Slide;

/**
 * Renders a slide to one self-contained SVG string: font and image embedded as
 * `data:` URIs, every text line pinned to PHP's measured width via `textLength`.
 * The SVG is the canonical artifact — served at the API, rasterised for export,
 * previewed in the admin — so there is one renderer and no drift.
 */
final readonly class SlideRenderer
{
    private const string DEFAULT_BG = '#0b1120';

    private const string DEFAULT_TEXT = '#f8fafc';

    private const string DEFAULT_ACCENT = '#38bdf8';

    public function __construct(
        private FormatRegistry $formats,
        private FontResolver $fonts,
        private AssetEmbedder $embedder,
        private TextLayout $textLayout,
        private SlideGeometry $geometry,
        private MediaResolver $media,
    ) {
    }

    /**
     * @return array<int, string> index => SVG string
     */
    public function renderDeck(Carousel $carousel, ?Creator $creator = null): array
    {
        $out = [];
        $count = \count($carousel->slides);
        for ($index = 0; $index < $count; ++$index) {
            $out[$index] = $this->renderSlide($carousel, $index, $creator);
        }

        return $out;
    }

    public function renderSlide(Carousel $carousel, int $index, ?Creator $creator = null): string
    {
        $slide = $carousel->slides[$index] ?? null;
        if (! $slide instanceof Slide) {
            return '';
        }

        $width = $this->formats->width($carousel->format);
        $height = $this->formats->height($carousel->format);
        $total = \count($carousel->slides);

        $bg = $this->color($carousel, $slide, 'bg', self::DEFAULT_BG);
        $text = $this->color($carousel, $slide, 'text', self::DEFAULT_TEXT);
        $accent = $this->color($carousel, $slide, 'accent', self::DEFAULT_ACCENT);

        $headingFile = $this->fonts->headingFile($carousel->fontPairing);
        $bodyFile = $this->fonts->bodyFile($carousel->fontPairing);

        $defs = $this->embedder->fontFace('rp-heading', $headingFile)
            .$this->embedder->fontFace('rp-body', $bodyFile);

        $body = '<rect width="'.$width.'" height="'.$height.'" fill="'.$bg.'"/>';
        $body .= $this->renderImage($slide, $width, $height, $index);
        $body .= $this->renderEffect($slide->background, $index, $width, $height, $total);
        $body .= $this->renderText($slide, $width, $height, $headingFile, $bodyFile, $text, $accent);
        $body .= $this->renderCreator($carousel, $creator, $index, $total, $width, $height, $text, $accent);
        $body .= $this->renderCounter($carousel, $index, $total, $width, $height, $bodyFile, $text, $accent);
        $body .= $this->renderSwipe($slide, $width, $height, $text, $accent);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" '
            .'width="'.$width.'" height="'.$height.'" font-family="rp-body">'
            .'<defs><style>'.$defs.'</style>'
            .'<clipPath id="frame-'.$index.'"><rect width="'.$width.'" height="'.$height.'"/></clipPath></defs>'
            .$body
            .'</svg>';
    }

    private function renderImage(Slide $slide, int $width, int $height, int $index): string
    {
        if (null === $slide->image) {
            return '';
        }

        $dims = $this->media->sourceDimensions($slide->image->media);
        $placement = $this->geometry->place(
            $dims[0] ?? $width,
            $dims[1] ?? $height,
            $slide->image->focusX,
            $slide->image->focusY,
            $slide->image->zoom,
            $width,
            $height,
        );

        $path = $this->media->derivativePath($slide->image->media, $placement->filter);
        $dataUri = null === $path ? null : $this->embedder->imageDataUri($path);

        if (null === $dataUri) {
            // Missing media: a flat placeholder rather than a broken or blank slide.
            return '<rect width="'.$width.'" height="'.$height.'" fill="#334155"/>';
        }

        $svg = '<g clip-path="url(#frame-'.$index.')">'
            .'<image href="'.$dataUri.'" x="'.$this->n($placement->x).'" y="'.$this->n($placement->y).'" '
            .'width="'.$this->n($placement->width).'" height="'.$this->n($placement->height).'" '
            .'preserveAspectRatio="none"/></g>';

        if ($slide->overlay > 0.0) {
            $svg .= '<rect width="'.$width.'" height="'.$height.'" fill="#000" fill-opacity="'.$this->n($slide->overlay).'"/>';
        }

        return $svg;
    }

    private function renderText(
        Slide $slide,
        int $width,
        int $height,
        string $headingFile,
        string $bodyFile,
        string $text,
        string $accent,
    ): string {
        $marginX = $width * 0.08;
        $areaW = $width - 2 * $marginX;
        $scale = $slide->textScale;

        /** @var list<array{laid: LaidOutText, font: string, color: string, upper: bool}> $blocks */
        $blocks = [];
        if (null !== $slide->tagline) {
            $blocks[] = ['laid' => $this->textLayout->layout($slide->tagline, $bodyFile, $areaW, $height * 0.15, $width * 0.035 * $scale, $width * 0.025), 'font' => 'rp-body', 'color' => $accent, 'upper' => true];
        }

        if (null !== $slide->title) {
            $blocks[] = ['laid' => $this->textLayout->layout($slide->title, $headingFile, $areaW, $height * 0.5, $width * 0.11 * $scale, $width * 0.045), 'font' => 'rp-heading', 'color' => $text, 'upper' => false];
        }

        if (null !== $slide->paragraph) {
            $blocks[] = ['laid' => $this->textLayout->layout($slide->paragraph, $bodyFile, $areaW, $height * 0.35, $width * 0.034 * $scale, $width * 0.022), 'font' => 'rp-body', 'color' => $text, 'upper' => false];
        }

        if ([] === $blocks) {
            return '';
        }

        $gap = $width * 0.03;
        $stackHeight = -$gap;
        foreach ($blocks as $block) {
            $stackHeight += $block['laid']->height() + $gap;
        }

        $marginY = $height * 0.09;
        $startY = match ($slide->layout) {
            'top' => $marginY,
            'center' => ($height - $stackHeight) / 2,
            default => $height - $marginY - $stackHeight,
        };

        [$anchor, $anchorX] = match ($slide->align) {
            'center' => ['middle', $width / 2],
            'right' => ['end', $width - $marginX],
            default => ['start', $marginX],
        };

        $svg = '';
        $y = $startY;
        foreach ($blocks as $block) {
            $svg .= $this->renderBlock($block['laid'], $anchor, $anchorX, $y, $block['font'], $block['color'], $block['upper']);
            $y += $block['laid']->height() + $gap;
        }

        return $svg;
    }

    private function renderBlock(LaidOutText $laid, string $anchor, float $anchorX, float $top, string $font, string $color, bool $upper): string
    {
        $svg = '';
        $y = $top;
        foreach ($laid->lines as $line) {
            $content = $upper ? mb_strtoupper($line->text) : $line->text;
            $baseline = $y + $laid->fontSize * 0.82;
            $svg .= '<text x="'.$this->n($anchorX).'" y="'.$this->n($baseline).'" '
                .'font-family="'.$font.'" font-size="'.$this->n($laid->fontSize).'" '
                .'fill="'.$color.'" text-anchor="'.$anchor.'" '
                .'textLength="'.$this->n($line->width).'" lengthAdjust="spacingAndGlyphs"'
                .($upper ? ' letter-spacing="'.$this->n($laid->fontSize * 0.06).'"' : '')
                .'>'.$this->escape($content).'</text>';
            $y += $laid->lineHeight;
        }

        return $svg;
    }

    private function renderCounter(Carousel $carousel, int $index, int $total, int $width, int $height, string $bodyFile, string $text, string $accent): string
    {
        $counter = $carousel->counter ?? new Counter();
        $style = $counter->style;
        if ('none' === $style || $total < 2) {
            return '';
        }

        $align = $counter->align;
        $marginX = $width * 0.08;
        $y = $height * 0.06;
        [$anchor, $x] = match ($align) {
            'left' => ['start', $marginX],
            'center' => ['middle', $width / 2],
            default => ['end', $width - $marginX],
        };

        if ('dots' === $style) {
            $dots = '';
            $size = $width * 0.012;
            $gap = $size * 2.4;
            $startX = $x - match ($anchor) {
                'end' => ($total - 1) * $gap,
                'middle' => ($total - 1) * $gap / 2,
                default => 0.0,
            };
            for ($i = 0; $i < $total; ++$i) {
                $dots .= '<circle cx="'.$this->n($startX + $i * $gap).'" cy="'.$this->n($y).'" r="'.$this->n($size).'" '
                    .'fill="'.($i === $index ? $accent : $text).'" fill-opacity="'.($i === $index ? '1' : '0.35').'"/>';
            }

            return $dots;
        }

        if ('bar' === $style) {
            $barW = $width - 2 * $marginX;
            $progress = ($index + 1) / $total;

            return '<rect x="'.$this->n($marginX).'" y="'.$this->n($y).'" width="'.$this->n($barW).'" height="6" rx="3" fill="'.$text.'" fill-opacity="0.25"/>'
                .'<rect x="'.$this->n($marginX).'" y="'.$this->n($y).'" width="'.$this->n($barW * $progress).'" height="6" rx="3" fill="'.$accent.'"/>';
        }

        $label = ($index + 1).' / '.$total;
        $size = $width * 0.03;
        $lineWidth = $this->textLayout->measureWidth($label, $bodyFile, $size);

        return '<text x="'.$this->n($x).'" y="'.$this->n($y + $size * 0.82).'" font-family="rp-body" font-size="'.$this->n($size).'" '
            .'fill="'.$text.'" fill-opacity="0.7" text-anchor="'.$anchor.'" '
            .'textLength="'.$this->n($lineWidth).'" lengthAdjust="spacingAndGlyphs">'.$this->escape($label).'</text>';
    }

    /**
     * The creator byline (avatar + name + role), shown on the slides selected by
     * `creatorOnSlides` (all / intro-outro / first). Top-left, so it never clashes
     * with bottom-anchored text or the top-right counter.
     */
    private function renderCreator(Carousel $carousel, ?Creator $creator, int $index, int $total, int $width, int $height, string $text, string $accent): string
    {
        if (! $creator instanceof Creator || ! $this->showsCreator($carousel->creatorOnSlides, $index, $total)) {
            return '';
        }

        $marginX = $width * 0.08;
        $top = $height * 0.05;
        $d = $width * 0.09;
        $cx = $marginX + $d / 2;
        $cy = $top + $d / 2;
        $svg = '';
        $textX = $marginX;

        if (null !== $creator->avatar) {
            $path = $this->media->derivativePath($creator->avatar, 'md');
            $dataUri = null === $path ? null : $this->embedder->imageDataUri($path);
            if (null !== $dataUri) {
                $svg .= '<clipPath id="rp-av-'.$index.'"><circle cx="'.$this->n($cx).'" cy="'.$this->n($cy).'" r="'.$this->n($d / 2).'"/></clipPath>'
                    .'<image href="'.$dataUri.'" x="'.$this->n($cx - $d / 2).'" y="'.$this->n($cy - $d / 2).'" width="'.$this->n($d).'" height="'.$this->n($d).'" '
                    .'clip-path="url(#rp-av-'.$index.')" preserveAspectRatio="xMidYMid slice"/>'
                    .'<circle cx="'.$this->n($cx).'" cy="'.$this->n($cy).'" r="'.$this->n($d / 2).'" fill="none" stroke="'.$accent.'" stroke-width="'.$this->n($d * 0.04).'"/>';
                $textX = $cx + $d / 2 + $width * 0.02;
            }
        }

        $nameSize = $width * 0.03;
        $svg .= '<text x="'.$this->n($textX).'" y="'.$this->n($cy - $nameSize * 0.1).'" font-family="rp-body" font-size="'.$this->n($nameSize).'" fill="'.$text.'" font-weight="600">'.$this->escape($creator->name).'</text>';
        if (null !== $creator->role) {
            $roleSize = $width * 0.022;
            $svg .= '<text x="'.$this->n($textX).'" y="'.$this->n($cy + $roleSize * 1.1).'" font-family="rp-body" font-size="'.$this->n($roleSize).'" fill="'.$text.'" fill-opacity="0.65">'.$this->escape($creator->role).'</text>';
        }

        return $svg;
    }

    private function showsCreator(string $onSlides, int $index, int $total): bool
    {
        return match ($onSlides) {
            'all' => true,
            'first' => 0 === $index,
            'intro-outro' => 0 === $index || $index === $total - 1,
            default => false,
        };
    }

    /**
     * A bottom-right "→" affordance hinting the reader to swipe on. Per-slide
     * (`swipe`), drafted onto the cover; an accent-tinted disc so it reads on a
     * photo, the arrow in the text colour so it always contrasts the background.
     */
    private function renderSwipe(Slide $slide, int $width, int $height, string $text, string $accent): string
    {
        if (! $slide->swipe) {
            return '';
        }

        $r = $width * 0.05;
        $margin = $width * 0.06;
        $cx = $width - $margin - $r;
        $cy = $height - $margin - $r;

        $tail = $cx - $r * 0.42;
        $tip = $cx + $r * 0.42;
        $head = $r * 0.34;

        return '<circle cx="'.$this->n($cx).'" cy="'.$this->n($cy).'" r="'.$this->n($r).'" '
            .'fill="'.$accent.'" fill-opacity="0.18" stroke="'.$accent.'" stroke-opacity="0.6" stroke-width="'.$this->n($width * 0.004).'"/>'
            .'<path d="M '.$this->n($tail).' '.$this->n($cy).' H '.$this->n($tip)
            .' M '.$this->n($tip - $head).' '.$this->n($cy - $head).' L '.$this->n($tip).' '.$this->n($cy).' L '.$this->n($tip - $head).' '.$this->n($cy + $head).'" '
            .'fill="none" stroke="'.$text.'" stroke-width="'.$this->n($width * 0.007).'" stroke-linecap="round" stroke-linejoin="round"/>';
    }

    /**
     * A deck-wide decorative layer, authored on a `total × width` canvas and shown
     * through the frame window at `-index × width` so a shape resumes across slides.
     */
    private function renderEffect(string $effect, int $index, int $width, int $height, int $total): string
    {
        if ('none' === $effect) {
            return '';
        }

        if ('paper' === $effect) {
            return '<filter id="rp-paper-'.$index.'"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" stitchTiles="stitch"/>'
                .'<feColorMatrix type="saturate" values="0"/></filter>'
                .'<rect width="'.$width.'" height="'.$height.'" filter="url(#rp-paper-'.$index.')" opacity="0.05"/>';
        }

        if ('poly-grid' === $effect) {
            $cell = round($width * 0.06);

            return '<defs><pattern id="rp-grid-'.$index.'" width="'.$cell.'" height="'.$cell.'" patternUnits="userSpaceOnUse" patternTransform="translate('.$this->n(-$index * $width).' 0)">'
                .'<path d="M '.$cell.' 0 L 0 0 0 '.$cell.'" fill="none" stroke="#fff" stroke-opacity="0.12" stroke-width="1.5"/></pattern></defs>'
                .'<rect width="'.$width.'" height="'.$height.'" fill="url(#rp-grid-'.$index.')"/>';
        }

        // blobs / bubbles / sketchy: shapes spread across the whole deck, windowed per slide.
        $shapes = '';
        for ($i = 0; $i <= $total; ++$i) {
            $cx = $i * $width + ($i % 2) * $width * 0.5;
            $cy = ($i % 3) * $height * 0.38 + $height * 0.1;
            if ('sketchy' === $effect) {
                $shapes .= '<path d="M '.$this->n($cx).' '.$this->n($cy).' q '.$this->n($width * 0.18).' '.$this->n(-$height * 0.05).' '.$this->n($width * 0.36).' 0" '
                    .'fill="none" stroke="#fff" stroke-opacity="0.14" stroke-width="'.$this->n($width * 0.006).'" stroke-linecap="round"/>';
            } elseif ('bubbles' === $effect) {
                // A cluster of thin rings with one faintly-filled bubble — deterministic per i.
                $shapes .= '<circle cx="'.$this->n($cx).'" cy="'.$this->n($cy).'" r="'.$this->n($width * 0.16).'" fill="none" stroke="#fff" stroke-opacity="0.14" stroke-width="'.$this->n($width * 0.006).'"/>'
                    .'<circle cx="'.$this->n($cx + $width * 0.14).'" cy="'.$this->n($cy + $height * 0.06).'" r="'.$this->n($width * 0.06).'" fill="#fff" fill-opacity="0.05"/>'
                    .'<circle cx="'.$this->n($cx - $width * 0.1).'" cy="'.$this->n($cy - $height * 0.05).'" r="'.$this->n($width * 0.04).'" fill="none" stroke="#fff" stroke-opacity="0.12" stroke-width="'.$this->n($width * 0.004).'"/>';
            } else {
                $shapes .= '<ellipse cx="'.$this->n($cx).'" cy="'.$this->n($cy).'" rx="'.$this->n($width * 0.32).'" ry="'.$this->n($width * 0.28).'" fill="#fff" fill-opacity="0.06"/>';
            }
        }

        return '<g clip-path="url(#frame-'.$index.')"><g transform="translate('.$this->n(-$index * $width).' 0)">'.$shapes.'</g></g>';
    }

    private function color(Carousel $carousel, Slide $slide, string $role, string $default): string
    {
        return $this->paletteColor($slide->palette, $role)
            ?? $this->paletteColor($carousel->palette, $role)
            ?? $default;
    }

    private function paletteColor(?Palette $palette, string $role): ?string
    {
        if (null === $palette) {
            return null;
        }

        return match ($role) {
            'bg' => $palette->bg,
            'text' => $palette->text,
            'accent' => $palette->accent,
            default => null,
        };
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_XML1, 'UTF-8');
    }

    /**
     * Format a number for SVG output: trim to 2 decimals, drop trailing zeros.
     */
    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
