<?php

namespace Pushword\Repurpose\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The background image of a slide, addressed by media filename plus a focal point
 * and zoom. The crop is computed at render time from these three numbers (never
 * baked into a cached derivative), so the same values stay correct when the slide
 * is re-rendered at another ratio — see {@see \Pushword\Repurpose\Service\SlideGeometry}.
 */
class SlideImage
{
    public function __construct(
        #[Assert\NotBlank(message: 'repurpose.image.media.empty')]
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
    ) {
    }
}
