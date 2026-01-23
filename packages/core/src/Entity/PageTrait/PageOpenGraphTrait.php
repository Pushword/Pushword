<?php

namespace Pushword\Core\Entity\PageTrait;

/**
 * OgTitle
 * OgDescription
 * OgImage.
 */
trait PageOpenGraphTrait
{
    abstract public function getCustomPropertyScalar(string $name): bool|float|int|string|null;

    abstract public function hasCustomProperty(string $name): bool;

    abstract public function setCustomProperty(string $name, mixed $value): void;

    public function getOgTitle(): ?string
    {
        return $this->hasCustomProperty('ogTitle') ? (string) $this->getCustomPropertyScalar('ogTitle') : null;
    }

    public function setOgTitle(?string $ogTitle): self
    {
        $this->setCustomProperty('ogTitle', $ogTitle);

        return $this;
    }

    public function getOgDescription(): ?string
    {
        return $this->hasCustomProperty('ogDescription') ? (string) $this->getCustomPropertyScalar('ogDescription') : null;
    }

    public function setOgDescription(?string $ogDescription): self
    {
        $this->setCustomProperty('ogDescription', $ogDescription);

        return $this;
    }

    public function getOgImage(): ?string
    {
        return $this->hasCustomProperty('ogImage') ? (string) $this->getCustomPropertyScalar('ogImage') : null;
    }

    public function setOgImage(?string $ogImage): self
    {
        $this->setCustomProperty('ogImage', $ogImage);

        return $this;
    }

    // TwitterCard

    public function getTwitterCard(): ?string
    {
        return $this->hasCustomProperty('twitterCard') ? (string) $this->getCustomPropertyScalar('twitterCard') : null;
    }

    public function setTwitterCard(?string $twitterCard): self
    {
        $this->setCustomProperty('twitterCard', $twitterCard);

        return $this;
    }

    public function getTwitterSite(): ?string
    {
        return $this->hasCustomProperty('twitterSite') ? (string) $this->getCustomPropertyScalar('twitterSite') : null;
    }

    public function setTwitterSite(?string $twitterSite): self
    {
        $this->setCustomProperty('twitterSite', $twitterSite);

        return $this;
    }

    public function getTwitterCreator(): ?string
    {
        return $this->hasCustomProperty('twitterCreator') ? (string) $this->getCustomPropertyScalar('twitterSite') : null;
    }

    public function setTwitterCreator(?string $twitterCreator): self
    {
        $this->setCustomProperty('twitterCreator', $twitterCreator);

        return $this;
    }

    // ------------
}
