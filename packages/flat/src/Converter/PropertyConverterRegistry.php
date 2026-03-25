<?php

namespace Pushword\Flat\Converter;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Registry for property converters used during flat file import/export.
 */
final class PropertyConverterRegistry
{
    /** @var array<string, FlatPropertyConverterInterface> */
    private array $converters = [];

    /** @var array<string, true> */
    private array $ignoredProperties = [];

    /**
     * @param iterable<FlatPropertyConverterInterface> $converters
     * @param string[]                                 $ignoredProperties
     */
    public function __construct(
        #[AutowireIterator('pushword.flat.property_converter')]
        iterable $converters,
        array $ignoredProperties = [],
    ) {
        foreach ($converters as $converter) {
            $this->converters[$converter->getPropertyName()] = $converter;
        }

        foreach ($ignoredProperties as $property) {
            $this->ignoredProperties[$property] = true;
        }
    }

    public function isIgnored(string $property): bool
    {
        return isset($this->ignoredProperties[$property]);
    }

    public function hasConverter(string $property): bool
    {
        return isset($this->converters[$property]);
    }

    public function toFlatValue(string $property, mixed $value): mixed
    {
        if ($this->isIgnored($property)) {
            return null;
        }

        if (! $this->hasConverter($property)) {
            return $value;
        }

        return $this->converters[$property]->toFlatValue($value);
    }

    public function fromFlatValue(string $property, mixed $value): mixed
    {
        if ($this->isIgnored($property)) {
            return null;
        }

        if (! $this->hasConverter($property)) {
            return $value;
        }

        return $this->converters[$property]->fromFlatValue($value);
    }
}
