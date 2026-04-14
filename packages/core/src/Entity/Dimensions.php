<?php

namespace Pushword\Core\Entity;

use Pushword\Core\Utils\ImageRatioLabeler;

final readonly class Dimensions
{
    public function __construct(
        public int $width,
        public int $height,
    ) {
    }

    public function getRatio(): float
    {
        return round($this->width / $this->height, 2);
    }

    public function getRatioLabel(): string
    {
        return ImageRatioLabeler::fromDimensions($this->width, $this->height);
    }

    /** @return array{0: int, 1: int} */
    public function toArray(): array
    {
        return [$this->width, $this->height];
    }
}
