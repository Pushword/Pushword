<?php

namespace Pushword\Core\Service;

use Pushword\Core\Entity\MediaInterface;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

/**
 * @implements NamerInterface<MediaInterface>
 */
final class VichUploadPropertyNamer implements NamerInterface
{
    public function name($object, PropertyMapping $mapping): string
    {
        return $object->getMedia();
    }
}
