<?php

namespace Pushword\Core\Service;

use Vich\UploaderBundle\Mapping\PropertyMapping;

//implements NamerInterface, ConfigurableInterface
class VichUploadPropertyNamer extends \Vich\UploaderBundle\Naming\PropertyNamer
{
    public function name($object, PropertyMapping $mapping): string
    {
        return strtolower(parent::name($object, $mapping));
    }
}
