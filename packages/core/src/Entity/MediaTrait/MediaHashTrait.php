<?php

namespace Pushword\Core\Entity\MediaTrait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;

/** @noRector */
trait MediaHashTrait
{
    /**
     * @ORM\Column(type="binary", length=20, options={"default": ""})
     *
     * @var ?string
     */
    protected $hash = null;

    abstract public function getMediaFile(): ?File;

    abstract public function getStoreIn(): ?string;

    abstract public function getMedia(): ?string;

    public function getHash(): string
    {
        return null === $this->hash ? $this->setHash()->getHash() : $this->hash;
    }

    public function resetHash(): self
    {
        $this->hash = null;

        return $this;
    }

    public function setHash(): self
    {
        if (($mediaFile = $this->getMediaFile()) !== null && file_exists($mediaFile)) {
            $this->hash = \Safe\sha1_file($mediaFile->getPathname(), true);

            return $this;
        }

        $this->hash = \Safe\sha1_file($this->getStoreIn().'/'.$this->getMedia(), true);

        return $this;
    }
}
