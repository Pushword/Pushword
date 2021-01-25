<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

interface FilterInterface
{
    /**
     * @param mixed $propertyValue
     *
     * @return mixed
     */
    public function apply($propertyValue);
}
