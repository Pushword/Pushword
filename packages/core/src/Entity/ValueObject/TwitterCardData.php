<?php

declare(strict_types=1);

namespace Pushword\Core\Entity\ValueObject;

final readonly class TwitterCardData
{
    public function __construct(
        public ?string $card = null,
        public ?string $site = null,
        public ?string $creator = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->card
            && null === $this->site
            && null === $this->creator;
    }
}
