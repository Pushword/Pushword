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
        #[Assert\Choice(callback: [BackgroundEffectRegistry::class, 'keys'], message: 'repurpose.slide.background.invalid')]
        public string $background = 'none',
        #[Assert\Valid]
        public ?Palette $palette = null,
        #[Assert\Valid]
        public ?SlideImage $image = null,
    ) {
    }

    /**
     * A slide must show *something*: at least one text field or a background image.
     * A fully empty slide is almost always an authoring mistake.
     */
    #[Assert\Callback]
    public function validateNotEmpty(ExecutionContextInterface $context): void
    {
        $hasText = null !== $this->tagline || null !== $this->title || null !== $this->paragraph;
        if ($hasText || null !== $this->image) {
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
        if ($this->overlay > 0.0 && null === $this->image) {
            $context->buildViolation('repurpose.slide.overlay.noImage')
                ->atPath('overlay')
                ->addViolation();
        }
    }
}
