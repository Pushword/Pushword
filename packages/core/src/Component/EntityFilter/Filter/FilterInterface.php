<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

interface FilterInterface
{
    /**
     * @return mixed
     */
    public function apply(mixed $propertyValue);
}
