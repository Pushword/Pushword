<?php

namespace Pushword\Core\Entity\MediaTrait;

use Doctrine\ORM\Mapping as ORM;

/** @noRector */
trait MediaHashTrait
{
    /**
     * @ORM\Column(type="binary", length=20, options={"default": ""})
     *
     * @var ?string
     */
    protected $hash = null;

    /**
     * @return ?string
     */
    public function getHash()
    {
        return null !== $this->getMediaFile() ? \Safe\sha1_file($this->getMediaFile()->getPathname(), true) : $this->hash;
    }

    /**
     * @param ?string $hash
     */
    public function setHash($hash = null): self
    {
        $hash = null === $hash ? $this->getHash() : null;
        $this->hash = null === $hash ? \Safe\sha1_file($this->getStoreIn().'/'.$this->getMedia(), true) : $hash;

        return $this;
    }
}
