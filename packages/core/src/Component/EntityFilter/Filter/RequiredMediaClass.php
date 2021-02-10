<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

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
