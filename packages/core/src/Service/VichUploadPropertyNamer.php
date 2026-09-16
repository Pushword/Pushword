<?php

declare(strict_types=1);

namespace Pushword\Core\Service;

use LogicException;
use Pushword\Core\Entity\Media;
use Vich\UploaderBundle\Mapping\PropertyMappingInterface;
use Vich\UploaderBundle\Naming\NamerInterface;

/**
 * @implements NamerInterface<Media>
 */
final class VichUploadPropertyNamer implements NamerInterface
{
    /**
     * @param object|mixed[] $object
     */
    public function name(object|array $object, PropertyMappingInterface $mapping): string
    {
        if (! $object instanceof Media) {
            throw new LogicException();
        }

        return $object->getFileName();
    }
}
