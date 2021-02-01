<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\PageInterface;

trait PageExtendedTrait
{
    /**
     * @ORM\ManyToOne(targetEntity="Pushword\Core\Entity\PageInterface")
     */
    protected ?PageInterface $extendedPage;

    public function getExtendedPage(): ?PageInterface
    {
        return $this->extendedPage;
    }

    public function setExtendPage(?PageInterface $extendedPage): PageInterface
    {
        $this->extendedPage = $extendedPage;

        return $this;
    }
}
