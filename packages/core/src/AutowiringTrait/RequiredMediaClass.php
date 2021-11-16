<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Entity\MediaInterface;

trait RequiredMediaClass
{
    /**
     * @var class-string<MediaInterface>
     */
    private string $mediaClass;

    /** @required
     * @param class-string<MediaInterface> $mediaClass
     */
    public function setMediaClass(string $mediaClass): self
    {
        $this->mediaClass = $mediaClass;

        return $this;
    }

    /**
     * @return class-string<MediaInterface>
     */
    public function getMediaClass(): string
    {
        return $this->mediaClass;
    }
}
