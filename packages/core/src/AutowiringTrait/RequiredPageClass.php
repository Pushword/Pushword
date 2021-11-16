<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Entity\PageInterface;

trait RequiredPageClass
{
    /**
     * @var class-string<PageInterface>
     */
    private string $pageClass;

    /**
     * @required
     *
     * @param class-string<PageInterface> $pageClass
     */
    public function setPageClass(string $pageClass): void
    {
        $this->pageClass = $pageClass;
    }

    /**
     * @return class-string<PageInterface>
     */
    public function getPageClass(): string
    {
        return $this->pageClass; // @phpstan-ignore-line
    }
}
