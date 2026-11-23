<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Model;

use Pushword\Repurpose\Validator\LibraryImage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * An image cropped to its frame at render time. The free layout defines that
 * frame as fractions of the slide, so it can be rendered at any output ratio.
 */
class SlideImage
{
    public function __construct(
        #[Assert\NotBlank(message: 'repurpose.image.media.empty')]
        #[LibraryImage]
        public string $media = '',
        /** 0..1 focal point in the source, x axis (0 = left edge kept, 1 = right). */
        #[Assert\Range(notInRangeMessage: 'repurpose.image.focus.range', min: 0, max: 1)]
        public float $focusX = 0.5,
        /** 0..1 focal point in the source, y axis (0 = top kept, 1 = bottom). */
        #[Assert\Range(notInRangeMessage: 'repurpose.image.focus.range', min: 0, max: 1)]
        public float $focusY = 0.5,
        /** ≥ 1, relative to cover-fit: 1.0 fills the frame, 1.4 punches in 40%. */
        #[Assert\Range(notInRangeMessage: 'repurpose.image.zoom.range', min: 1, max: 5)]
        public float $zoom = 1.0,
        #[Assert\Range(min: 0, max: 1)]
        public float $x = 0.25,
        #[Assert\Range(min: 0, max: 1)]
        public float $y = 0.25,
        #[Assert\Range(min: 0.05, max: 1)]
        public float $width = 0.5,
        #[Assert\Range(min: 0.05, max: 1)]
        public float $height = 0.5,
    ) {
    }

    #[Assert\Callback]
    public function validateFrame(ExecutionContextInterface $context): void
    {
        if ($this->x + $this->width > 1.0) {
            $context->buildViolation('repurpose.image.frame.outsideSlide')->atPath('width')->addViolation();
        }

        if ($this->y + $this->height > 1.0) {
            $context->buildViolation('repurpose.image.frame.outsideSlide')->atPath('height')->addViolation();
        }
    }
}
