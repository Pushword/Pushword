<?php

namespace Pushword\Repurpose\Model;

/**
 * The result of laying out a text block into a fixed frame: the wrapped lines
 * (each with its measured width), the font size that made them fit, the line
 * advance, and whether the text still overflowed at the minimum size.
 */
final readonly class LaidOutText
{
    /**
     * @param TextLine[] $lines
     */
    public function __construct(
        public array $lines,
        public float $fontSize,
        public float $lineHeight,
        public bool $overflow,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->lines;
    }

    public function height(): float
    {
        return \count($this->lines) * $this->lineHeight;
    }

    public function maxWidth(): float
    {
        $max = 0.0;
        foreach ($this->lines as $line) {
            $max = max($max, $line->width);
        }

        return $max;
    }
}
