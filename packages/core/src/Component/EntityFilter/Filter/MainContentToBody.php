<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

class MainContentToBody extends AbstractFilter
{
    private string $body = '';

    /**
     * @return self
     */
    public function apply($propertyValue)
    {
        $this->body = $propertyValue;

        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }
}
