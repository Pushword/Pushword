<?php

namespace Pushword\Repurpose\Model;

use Pushword\Repurpose\Service\BackgroundEffectRegistry;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One slide of a carousel. Every text field is optional — a slide may be an image
 * with a title, a title with a paragraph, or a bare cover. The compositing order
 * is: palette bg → image (cropped) → overlay → background effect → text → creator
 * badge → counter.
 */
class Slide
{
    public const array LAYOUTS = ['top', 'center', 'bottom'];

    public const array ALIGNS = ['left', 'center', 'right'];

    /**
     * How a slide's images fill the frame: one image covering it (`full`), or two
     * stacked vertically (`split-v`) / side by side (`split-h`). The list is the
     * seam for further multi-image layouts (grids, thirds…) — each is just a set
     * of sub-frames the renderer places images into.
     */
    public const array IMAGE_LAYOUTS = ['full', 'split-v', 'split-h'];

    /**
     * @param list<SlideImage> $images
     */
    public function __construct(
        #[Assert\Choice(choices: self::LAYOUTS, message: 'repurpose.slide.layout.invalid')]
        public string $layout = 'bottom',
        #[Assert\Choice(choices: self::ALIGNS, message: 'repurpose.slide.align.invalid')]
        public string $align = 'left',
        public ?string $tagline = null,
        public ?string $title = null,
        public ?string $paragraph = null,
        public bool $swipe = false,
        /** Darkening of the image behind the text, 0 (none) … 1 (opaque). */
        #[Assert\Range(notInRangeMessage: 'repurpose.slide.overlay.range', min: 0, max: 1)]
        public float $overlay = 0.0,
        /** Multiplies the auto-fitted text size, 0.5 … 2. */
        #[Assert\Range(notInRangeMessage: 'repurpose.slide.textScale.range', min: 0.5, max: 2)]
        public float $textScale = 1.0,
        /** Overrides the deck's background effect for this slide; null inherits the deck. */
        #[Assert\Choice(callback: [BackgroundEffectRegistry::class, 'keys'], message: 'repurpose.slide.background.invalid')]
        public ?string $background = null,
        #[Assert\Valid]
        public ?Palette $palette = null,
        #[Assert\Choice(choices: self::IMAGE_LAYOUTS, message: 'repurpose.slide.imageLayout.invalid')]
        public string $imageLayout = 'full',
        #[Assert\Valid]
        public array $images = [],
    ) {
    }

    /**
     * True when the slide carries at least one image (the compositing stack paints
     * a photo behind the text).
     */
    public function hasImage(): bool
    {
        return [] !== $this->images;
    }

    /**
     * The slide's primary image (the whole frame for `full`, the first cell for a
     * split), or null when it has none — the anchor the contrast advisor samples.
     */
    public function firstImage(): ?SlideImage
    {
        return $this->images[0] ?? null;
    }

    /**
     * A slide must show *something*: at least one text field or a background image.
     * A fully empty slide is almost always an authoring mistake.
     */
    #[Assert\Callback]
    public function validateNotEmpty(ExecutionContextInterface $context): void
    {
        $hasText = null !== $this->tagline || null !== $this->title || null !== $this->paragraph;
        if ($hasText || $this->hasImage()) {
            return;
        }

        $context->buildViolation('repurpose.slide.empty')
            ->atPath('title')
            ->addViolation();
    }

    /**
     * A dark overlay only makes sense over an image; on a flat colour it just
     * muddies the palette. Flag it rather than silently ignoring it.
     */
    #[Assert\Callback]
    public function validateOverlay(ExecutionContextInterface $context): void
    {
        if ($this->overlay > 0.0 && ! $this->hasImage()) {
            $context->buildViolation('repurpose.slide.overlay.noImage')
                ->atPath('overlay')
                ->addViolation();
        }
    }

    /**
     * A split layout paints two cells, so it needs two images — one per cell.
     * Fewer would leave a cell empty; flag it rather than render a lopsided slide.
     */
    #[Assert\Callback]
    public function validateSplitImages(ExecutionContextInterface $context): void
    {
        if ('full' !== $this->imageLayout && \count($this->images) < 2) {
            $context->buildViolation('repurpose.slide.split.needsTwoImages')
                ->atPath('images')
                ->addViolation();
        }
    }
}
