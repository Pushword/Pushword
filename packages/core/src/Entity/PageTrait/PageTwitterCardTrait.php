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
        // @phpstan-ignore-next-line
        return $this->getCustomProperty('twitterCard');
    }

    public function setTwitterCard(?string $twitterCard): self
    {
        // @phpstan-ignore-next-line
        $this->setCustomProperty('twitterCard', $twitterCard);

        return $this;
    }

    public function getTwitterSite(): ?string
    {
        // @phpstan-ignore-next-line
        return $this->getCustomProperty('twitterSite');
    }

    public function setTwitterSite(?string $twitterSite): self
    {
        $this->setCustomProperty('twitterSite', $twitterSite);

        return $this;
    }

    public function getTwitterCreator(): ?string
    {
        // @phpstan-ignore-next-line
        return $this->getCustomProperty('twitterCreator');
    }

    public function setTwitterCreator(?string $twitterCreator): self
    {
        $this->setCustomProperty('twitterCreator', $twitterCreator);

        return $this;
    }
}
