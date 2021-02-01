<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\PageInterface;

trait PageParentTrait
{
    /**
     * @ORM\ManyToOne(targetEntity="Pushword\Core\Entity\PageInterface", inversedBy="childrenPages")
     */
    protected $parentPage;

    /**
     * @ORM\OneToMany(targetEntity="Pushword\Core\Entity\PageInterface", mappedBy="parentPage")
     * @ORM\OrderBy({"id": "ASC"})
     */
    protected $childrenPages;

    public function getParentPage(): ?self
    {
        return $this->parentPage;
    }

    public function setParentPage(?PageInterface $parentPage): self
    {
        $this->parentPage = $parentPage;

        return $this;
    }

    /**
     * @return ArrayCollection|PageInterface[]
     */
    public function getChildrenPages()
    {
        return $this->childrenPages;
    }
}
