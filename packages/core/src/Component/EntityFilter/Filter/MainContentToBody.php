<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

class MainContentToBody extends AbstractFilter
{
    private string $body = '';

    /**
     * @return self
     */
    public function apply($string)
    {
        $this->body = $string;

        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }
}
