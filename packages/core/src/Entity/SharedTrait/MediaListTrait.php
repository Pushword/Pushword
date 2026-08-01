<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\Media;

trait MediaListTrait
{
    /**
     * @var Collection<int, Media>
     */
    #[ORM\ManyToMany(targetEntity: Media::class)]
    public Collection $mediaList {
        get => $this->mediaList ??= new ArrayCollection();
        set {
            $this->mediaList = new ArrayCollection();

            foreach ($value as $media) {
                $this->addMedia($media);
            }
        }
    }

    public function addMedia(Media $media): self
    {
        if (! $this->mediaList->contains($media)) {
            $this->mediaList->add($media);
        }

        return $this;
    }

    public function removeMedia(Media $media): self
    {
        $this->mediaList->removeElement($media);

        return $this;
    }
}
