<?php

namespace Pushword\Core\Entity\ValueObject;

final readonly class OpenGraphData
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $image = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->title
            && null === $this->description
            && null === $this->image;
    }
}
