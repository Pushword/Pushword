<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\Media;

trait MediaListTrait
{
    /**
     * @var Collection<int, Media>|null
     */
    #[ORM\ManyToMany(targetEntity: Media::class)]
    protected ?Collection $mediaList = null;  // @phpstan-ignore-line

    /** @return Collection<int, Media> */
    public function getMediaList(): Collection
    {
        return $this->mediaList ?? ($this->mediaList = new ArrayCollection());
    }

    /** @param Collection<int, Media> $mediaList */
    public function setMediaList(Collection $mediaList): self
    {
        $this->mediaList = new ArrayCollection();

        foreach ($mediaList as $media) {
            $this->addMedia($media);
        }

        return $this;
    }

    public function addMedia(Media $media): self
    {
        if (! $this->getMediaList()->contains($media)) {
            $this->getMediaList()->add($media);
        }

        return $this;
    }

    public function removeMedia(Media $media): self
    {
        $this->getMediaList()->removeElement($media);

        return $this;
    }
}
