<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\PageHasMediaInterface as PageHasMedia;

trait PageImageTrait
{
    /**
     * @ORM\ManyToOne(
     *     targetEntity="Pushword\Core\Entity\MediaInterface",
     *     cascade={"all"},
     *     inversedBy="mainImagePages"
     * )
     */
    protected $mainImage;

    /**
     * @var ArrayCollection
     */
    protected $images;

    /**
     * @ORM\OneToMany(
     *     targetEntity="Pushword\Core\Entity\PageHasMediaInterface",
     *     mappedBy="page",
     *     cascade={"all"},
     *     orphanRemoval=true
     * )
     * @ORM\OrderBy({"position": "ASC"})
     */
    protected $pageHasMedias;

    public function __constructImage()
    {
        $this->pageHasMedias = new ArrayCollection();
    }

    public function setPageHasMedias($pageHasMedias)
    {
        $this->pageHasMedias = new ArrayCollection();
        foreach ($pageHasMedias as $pageHasMedia) {
            $this->addPageHasMedia($pageHasMedia);
        }
    }

    public function getPageHasMedias()
    {
        return $this->pageHasMedias;
    }

    public function addPageHasMedia(PageHasMedia $pageHasMedia): self
    {
        $pageHasMedia->setPage($this);
        $this->pageHasMedias[] = $pageHasMedia;

        return $this;
    }

    public function resetPageHasMedias(): void
    {
        foreach ($this->pageHasMedias as $pageHasMedia) {
            $this->removePageHasMedia($pageHasMedia);
        }
    }

    public function removePageHasMedia(PageHasMedia $pageHasMedia): void
    {
        $this->pageHasMedias->removeElement($pageHasMedia);
    }

    public function getMainImage(): ?MediaInterface
    {
        return $this->mainImage;
    }

    public function setMainImage(?MediaInterface $mainImage): self
    {
        // TODO: Déplacer en Assert pour éviter une erreur dégueu ?!
        if (null !== $mainImage && null === $mainImage->getWidth()) {
            throw new \Exception('mainImage must be an Image. Media imported is not an image');
        }

        $this->mainImage = $mainImage;

        return $this;
    }

    public function getImages(): Collection
    {
        if (! $this->images) {
            $this->images = new ArrayCollection();
            foreach ($this->pageHasMedias as $p) {
                if (null !== $p->getMedia()) {
                    $this->images[] = $p->getMedia();
                }
            }
        }

        return $this->images;
    }

    public function issetImage()
    {
        if ($this->getImages()->count() > 0) {
            return true;
        }

        return false;
    }
}
