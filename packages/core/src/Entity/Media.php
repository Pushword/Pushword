<?php

namespace Pushword\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesTrait;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @ORM\MappedSuperclass
 * @Vich\Uploadable
 * @ORM\HasLifecycleCallbacks
 * UniqueEntity({"media"}, message="Ce media semble déjà présent.")
 */
class Media implements MediaInterface
{
    use CustomPropertiesTrait;
    use IdTrait;
    use MediaTrait;

    public function __construct()
    {
        $this->updatedAt = null !== $this->updatedAt ? $this->updatedAt : new \DateTime();
        $this->createdAt = null !== $this->createdAt ? $this->createdAt : new \DateTime();
    }
}
