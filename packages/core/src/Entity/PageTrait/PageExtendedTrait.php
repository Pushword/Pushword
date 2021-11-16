<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\PageInterface;

trait PageExtendedTrait
{
    /**
     * @ORM\ManyToOne(targetEntity="Pushword\Core\Entity\PageInterface")
     */
    protected ?PageInterface $extendedPage = null;

    public function getExtendedPage(): ?PageInterface
    {
        return $this->extendedPage;
    }

    public function setExtendPage(?PageInterface $page): PageInterface
    {
        $this->extendedPage = $page;

        return $this;
    }
}
