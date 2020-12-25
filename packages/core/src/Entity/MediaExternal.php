<?php

namespace Pushword\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @ORM\MappedSuperclass
 * @Vich\Uploadable
 * @ORM\HasLifecycleCallbacks
 * UniqueEntity({"name"}, message="Ce nom existe déjà.")
 */
class MediaExternal extends Media
{
    protected $media = '';
    protected $slug = '';

    public static function load($src): self
    {
        $media = new self();
        $media->setRelativeDir($src);

        return $media;
    }

    public function getFullPath(): ?string
    {
        return $this->relativeDir;
    }
}
