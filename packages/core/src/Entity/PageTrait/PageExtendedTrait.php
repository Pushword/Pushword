<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\Page;

trait PageExtendedTrait
{
    #[ORM\ManyToOne(targetEntity: Page::class)]
    protected ?Page $extendedPage = null;  // @phpstan-ignore-line

    public function getExtendedPage(): ?Page
    {
        return $this->extendedPage;
    }

    public function setExtendPage(?Page $page): Page
    {
        $this->extendedPage = $page;

        return $this;
    }
}
