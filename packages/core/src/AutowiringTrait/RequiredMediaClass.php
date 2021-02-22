<?php

namespace Pushword\Core\AutowiringTrait;

trait RequiredMediaClass
{
    private string $mediaClass;

    /** @required */
    public function setMediaClass(string $mediaClass): self
    {
        $this->mediaClass = $mediaClass;

        return $this;
    }

    public function getMediaClass(): string
    {
        return $this->mediaClass;
    }
}
