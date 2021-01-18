<?php

namespace Pushword\Core\Utils;

class Entity
{
    public static function getProperties($object): array
    {
        $reflClass = new \ReflectionClass(\get_class($object));
        $properties = array_filter($reflClass->getProperties(), function (\ReflectionProperty $property) {
            if (false !== strpos($property->getDocComment(), '@ORM\Column')) {
                return true;
            }
        });
        foreach ($properties as $key => $property) {
            if ('id' == $property->getName()) {
                unset($properties[$key]);

                continue;
            }
            $properties[$key] = $property->getName();
        }

        return array_values($properties);
    }
}
