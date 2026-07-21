<?php

namespace Pushword\Repurpose\Model;

/**
 * One laid-out line of text with the pixel width PHP measured for it. That width
 * is emitted as the SVG `textLength`, so the browser renders the line at exactly
 * this width — binding the renderer to the validator's measurement.
 */
final readonly class TextLine
{
    public function __construct(
        public string $text,
        public float $width,
    ) {
    }
}
