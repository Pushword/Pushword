<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\Page;

trait PageExtendedTrait
{
    #[ORM\ManyToOne(targetEntity: Page::class)]
    public ?Page $extendedPage = null;  // @phpstan-ignore-line
}
