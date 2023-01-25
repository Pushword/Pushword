<?php

namespace Pushword\Core\Entity\PageTrait;

/**
 * TwiiterCad       “summary”, “summary_large_image”, “app”, or “player”.
 * TwitterSite      @Username
 * TwitterCreator   @Username
 * may add image/title/description ? IDEA.
 */
trait PageTwitterCardTrait
{
    public function getTwitterCard(): ?string
    {
        return $this->getCustomProperty('twitterCard'); // @phpstan-ignore-line
    }

    public function setTwitterCard(?string $twitterCard): self
    {
        $this->setCustomProperty('twitterCard', $twitterCard); // @phpstan-ignore-line

        return $this;
    }

    public function getTwitterSite(): ?string
    {
        return $this->getCustomProperty('twitterSite'); // @phpstan-ignore-line
    }

    public function setTwitterSite(?string $twitterSite): self
    {
        $this->setCustomProperty('twitterSite', $twitterSite);

        return $this;
    }

    public function getTwitterCreator(): ?string
    {
        return $this->getCustomProperty('twitterCreator'); // @phpstan-ignore-line
    }

    public function setTwitterCreator(?string $twitterCreator): self
    {
        $this->setCustomProperty('twitterCreator', $twitterCreator);

        return $this;
    }
}
