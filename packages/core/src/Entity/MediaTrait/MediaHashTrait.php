<?php

namespace Pushword\Core\Entity\MediaTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;

use function Safe\sha1_file;

use Symfony\Component\HttpFoundation\File\File;

trait MediaHashTrait
{
    /**
     * @var string|resource|null
     */
    #[ORM\Column(type: Types::BINARY, length: 20, options: ['default' => ''])]
    protected $hash;

    abstract public function getMediaFile(): ?File;

    abstract public function getStoreIn(): string;

    abstract public function getMedia(): string;

    public function getHash(): mixed
    {
        return $this->hash ?? $this->setHash()->getHash();
    }

    public function resetHash(): self
    {
        $this->hash = null;

        return $this;
    }

    public function setHash(?string $hash = null): self
    {
        if (null !== $hash) {
            $this->hash = $hash;

            return $this;
        }

        if (($mediaFile = $this->getMediaFile()) !== null && file_exists($mediaFile->getPathname())) {
            $this->hash = sha1_file($mediaFile->getPathname(), true);

            return $this;
        }

        $mediaFilePath = $this->getStoreIn().'/'.$this->getMedia();

        if (! file_exists($mediaFilePath) || ! is_file($mediaFilePath)) {
            throw new Exception('incredible ! `'.$mediaFilePath.'`');
        }

        $this->hash = sha1_file($mediaFilePath, true);

        return $this;
    }
}
