<?php

namespace Pushword\Core\Utils;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

class Entity
{
    /**
     * @param array<ReflectionAttribute<object>> $attributes
     */
    private static function containAttribute(array $attributes, string $searchedName): bool
    {
        return array_any(
            $attributes,
            static fn (ReflectionAttribute $attribute): bool => $attribute->getName() === $searchedName,
        );
    }

    /**
     * @param list<string> $attributesTypesToKeep
     *
     * @return list<string>
     */
    public static function getProperties(
        object $object,
        array $attributesTypesToKeep = [Column::class, ManyToOne::class, ManyToMany::class]
    ): array {
        $reflectionClass = new ReflectionClass($object::class);
        $properties = array_filter($reflectionClass->getProperties(), static function (ReflectionProperty $property) use ($attributesTypesToKeep) {
            $attributes = $property->getAttributes();
            foreach ($attributesTypesToKeep as $a) {
                if (self::containAttribute($attributes, $a)) {
                    return true;
                }
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
