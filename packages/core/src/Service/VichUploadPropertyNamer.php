<?php

namespace Pushword\Core\Service;

use Vich\UploaderBundle\Mapping\PropertyMapping;

final class VichUploadPropertyNamer extends \Vich\UploaderBundle\Naming\PropertyNamer
{
    public function name($object, PropertyMapping $mapping): string
    {
        return $object->getMedia(); // return strtolower(parent::name($object, $mapping));
    }
}
