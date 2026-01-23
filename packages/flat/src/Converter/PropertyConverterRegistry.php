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

    /**
     * @param iterable<FlatPropertyConverterInterface> $converters
     */
    public function __construct(
        #[AutowireIterator('pushword.flat.property_converter')]
        iterable $converters,
    ) {
        foreach ($converters as $converter) {
            $this->converters[$converter->getPropertyName()] = $converter;
        }
    }

    public function hasConverter(string $property): bool
    {
        return isset($this->converters[$property]);
    }

    public function toFlatValue(string $property, mixed $value): mixed
    {
        if (! $this->hasConverter($property)) {
            return $value;
        }

        return $this->converters[$property]->toFlatValue($value);
    }

    public function fromFlatValue(string $property, mixed $value): mixed
    {
        if (! $this->hasConverter($property)) {
            return $value;
        }

        return $this->converters[$property]->fromFlatValue($value);
    }
}
