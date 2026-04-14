<?php

namespace Pushword\Flat\Exporter;

use Pushword\Core\Entity\Page;
use ReflectionClass;

/**
 * @internal
 */
final class ExporterDefaultValueHelper
{
    /** @var array<string, mixed> */
    private array $defaultValueCache = [];

    /**
     * @param class-string|null $class
     */
    public function get(string $property, ?string $class = null): mixed
    {
        $class ??= Page::class;
        $propertyCacheKey = $class.'::'.$property;

        if (isset($this->defaultValueCache[$propertyCacheKey])) {
            return $this->defaultValueCache[$propertyCacheKey];
        }

        $reflection = new ReflectionClass($class);
        $reflectionProperty = $reflection->getProperty($property);
        $this->defaultValueCache[$propertyCacheKey] = $reflectionProperty->getDefaultValue();

        return $this->defaultValueCache[$propertyCacheKey];
    }
}
