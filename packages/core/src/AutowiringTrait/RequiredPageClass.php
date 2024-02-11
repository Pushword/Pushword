<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Entity\PageInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait RequiredPageClass
{
    /**
     * @var class-string<PageInterface>
     */
    private string $pageClass;

    /**
     * @param class-string<PageInterface> $pageClass
     */
    #[Required]
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
