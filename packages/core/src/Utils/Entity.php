<?php

namespace Pushword\Core\Utils;

use Doctrine\ORM\Mapping\Column;

class Entity
{
    /**
     * @return list<string>
     */
    public static function getProperties(object $object): array
    {
        $reflectionClass = new \ReflectionClass($object::class);
        $properties = array_filter($reflectionClass->getProperties(), function (\ReflectionProperty $property) {
            if (str_contains((string) $property->getDocComment(), '@ORM\Column')
                || str_contains(implode(',', $property->getAttributes()), Column::class)) {
                return true;
            }
        });
        $propertyNames = [];
        foreach ($properties as $property) {
            if ('id' === $property->getName()) {
                continue;
            }

            $propertyNames[] = $property->getName();
        }

        return $propertyNames;
    }
}
