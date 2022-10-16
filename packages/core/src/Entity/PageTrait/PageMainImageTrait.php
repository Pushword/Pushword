<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\MediaInterface;

trait PageMainImageTrait
{
    #[ORM\ManyToOne(targetEntity: \Pushword\Core\Entity\MediaInterface::class, cascade: ['persist'], inversedBy: 'mainImagePages')]
    protected ?MediaInterface $mainImage = null;

    public function getMainImage(): ?MediaInterface
    {
        return $this->mainImage;
    }

    public function setMainImage(?MediaInterface $media): self
    {
        // TODO: Déplacer en Assert pour éviter une erreur dégueu ?!
        if (null !== $media && null === $media->getWidth()) {
            throw new \Exception('mainImage must be an Image. Media imported is not an image');
        }

        $this->mainImage = $media;

        return $this;
    }
}
