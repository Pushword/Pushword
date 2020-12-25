<?php

namespace Pushword\Core\Entity\PageTrait;

use Pushword\Core\Component\Filter\FilterInterface;

trait PageContentTrait
{
    /** @var FilterInterface */
    protected $content;

    public function getContent()
    {
        return $this->content;
    }

    public function setContent(FilterInterface $content)
    {
        $this->content = $content;

        return $this;
    }
}
