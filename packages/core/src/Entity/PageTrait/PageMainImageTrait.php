<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\ORM\Mapping as ORM;
use Exception;
use Pushword\Core\Entity\Media;

trait PageMainImageTrait
{
    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'], inversedBy: 'mainImagePages')]
    protected ?Media $mainImage = null;  // @phpstan-ignore-line

    public function getMainImage(): ?Media
    {
        return $this->mainImage;
    }

    public function setMainImage(?Media $media): self
    {
        // TODO: Déplacer en Assert pour éviter une erreur dégueu ?!
        if (null !== $media && null === $media->getWidth()) {
            throw new Exception('mainImage must be an Image. Media imported is not an image');
        }

        $this->mainImage = $media;

        return $this;
    }
}
