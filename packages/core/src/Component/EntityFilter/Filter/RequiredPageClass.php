<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

trait RequiredPageClass
{
    private string $pageClass;

    /** @required */
    public function setMediaClass(string $pageClass): self
    {
        $this->pageClass = $pageClass;

        return $this;
    }

    public function getPageClass(): string
    {
        return $this->pageClass;
    }
}
