<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

abstract class AbstractFilter implements FilterInterface
{
    /**
     * @param mixed $propertyValue
     *
     * @return mixed
     */
    abstract public function apply($propertyValue);
}
