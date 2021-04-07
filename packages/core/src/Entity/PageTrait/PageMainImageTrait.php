<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\MediaInterface;

trait PageMainImageTrait
{
    /**
     * @ORM\ManyToOne(
     *     targetEntity="Pushword\Core\Entity\MediaInterface",
     *     cascade={"all"},
     *     inversedBy="mainImagePages"
     * )
     */
    protected $mainImage;

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

    public function setMainImageFormat($value): self
    {
        $this->setCustomProperty('mainImageFormat', $value);

        return $this;
    }

    public function getMainImageFormat()
    {
        return $this->getCustomProperty('mainImageFormat') ?? 1;
    }
}
