<?php

namespace Pushword\Core\Utils;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToOne;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

class Entity
{
    /**
     * @param ReflectionAttribute[] $attributes
     */
    private static function containAttribute(array $attributes, string $searchedName): bool // @phpstan-ignore-line
    {
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === $searchedName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function getProperties(object $object): array
    {
        $reflectionClass = new ReflectionClass($object::class);
        $properties = array_filter($reflectionClass->getProperties(), static function (ReflectionProperty $property) {
            $attributes = $property->getAttributes();
            if (self::containAttribute($attributes, Column::class)) {
                return true;
            }

            if (self::containAttribute($attributes, ManyToOne::class)) {
                return true;
            }
        });
        $propertyNames = [];
        foreach ($properties as $property) {
            // if ('id' === $property->getName()) {
            //     continue;
            // }

            $propertyNames[] = $property->getName();
        }

        return $propertyNames;
    }
}
