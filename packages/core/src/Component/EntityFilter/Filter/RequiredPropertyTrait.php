<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

trait RequiredPropertyTrait
{
    private string $property;

    public function setProperty(string $property): void
    {
        $this->property = $property;
    }

    public function getProperty(): string
    {
        return $this->property;
    }
}
