<?php

namespace Pushword\Core\Component\EntityFilter\ValueObject;

use Stringable;

final readonly class ContentBody implements Stringable
{
    public function __construct(
        private string $body,
    ) {
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function __toString(): string
    {
        return $this->body;
    }
}
