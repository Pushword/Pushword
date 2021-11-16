<?php

namespace Pushword\Core\Utils;

class Entity
{
    /**
     * @return list<string>
     */
    public static function getProperties(object $object): array
    {
        $reflectionClass = new \ReflectionClass(\get_class($object));
        $properties = array_filter($reflectionClass->getProperties(), function (\ReflectionProperty $property) {
            if (str_contains((string) $property->getDocComment(), '@ORM\Column')) {
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
