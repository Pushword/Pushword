<?php

namespace Pushword\Core\Entity\PageTrait;

/**
 * OgTitle
 * OgDescription
 * OgImage.
 */
trait PageOpenGraphTrait
{
    public function getOgTitle(): ?string
    {
        return $this->getCustomProperty('ogTitle');
    }

    public function setOgTitle(?string $ogTitle): self
    {
        $this->setCustomProperty('ogTitle', $ogTitle);

        return $this;
    }

    public function getOgDescription(): ?string
    {
        return $this->getCustomProperty('ogDescription');
    }

    public function setOgDescription(?string $ogDescription): self
    {
        $this->setCustomProperty('ogDescription', $ogDescription);

        return $this;
    }

    public function getOgImage(): ?string
    {
        return $this->getCustomProperty('ogImage');
    }

    public function setOgImage(?string $ogImage): self
    {
        $this->setCustomProperty('ogImage', $ogImage);

        return $this;
    }
}
