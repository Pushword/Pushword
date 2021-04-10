<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Pushword\Core\Entity\PageInterface;

trait PageParentTrait
{
    /**
     * @ORM\ManyToOne(targetEntity="Pushword\Core\Entity\PageInterface", inversedBy="childrenPages")
     * TODO: assert parentPage is not currentPage
     */
    protected $parentPage;

    /**
     * @ORM\OneToMany(targetEntity="Pushword\Core\Entity\PageInterface", mappedBy="parentPage")
     * @ORM\OrderBy({"publishedAt": "DESC", "priority": "DESC"})
     */
    protected $childrenPages;

    public function getParentPage(): ?self
    {
        return $this->parentPage;
    }

    public function setParentPage(?PageInterface $parentPage): self
    {
        if ($parentPage === $this) {
            throw new LogicException('Current Page can\'t be it own parent page.');
        }

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
