<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

interface FilterInterface
{
    public function apply(mixed $propertyValue); // @phpstan-ignore-line
}
