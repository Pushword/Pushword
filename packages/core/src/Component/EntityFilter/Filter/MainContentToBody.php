<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

class MainContentToBody extends AbstractFilter
{
    private string $body = '';

    public function apply(mixed $propertyValue): self
    {
        $this->body = \strval($propertyValue);

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
