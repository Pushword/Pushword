<?php

namespace Pushword\Core\Component\EntityFilter;

use Pushword\Core\Component\EntityFilter\Filter\FilterInterface;

final class FilterRegistry
{
    /** @var array<class-string<FilterInterface>, FilterInterface> */
    private array $filters = [];

    /** @var array<string, FilterInterface|null> Cache for short name lookups (backward compatibility) */
    private array $shortNameCache = [];

    /**
     * @param iterable<FilterInterface> $filters
     */
    public function __construct(
        iterable $filters,
    ) {
        foreach ($filters as $filter) {
            $this->filters[$filter::class] = $filter;
        }
    }

    public function getFilter(string $nameOrClass): ?FilterInterface
    {
        // Backward compatibility: lookup by short name (lazy resolution with caching)
        return $this->filters[$nameOrClass] ?? $this->resolveByShortName($nameOrClass);
    }

    private function resolveByShortName(string $name): ?FilterInterface
    {
        // Check cache first
        if (\array_key_exists($name, $this->shortNameCache)) {
            return $this->shortNameCache[$name];
        }

        // Normalize the name for comparison
        $normalizedName = lcfirst($name);

        // Search through filters for matching short name
        foreach ($this->filters as $className => $filter) {
            $shortName = $this->getFilterShortName($className);
            if ($shortName === $normalizedName) {
                $this->shortNameCache[$name] = $filter;

                return $filter;
            }
        }

        // Cache negative result to avoid repeated searches
        $this->shortNameCache[$name] = null;

        return null;
    }

    private function getFilterShortName(string $className): string
    {
        $parts = explode('\\', $className);
        $shortName = end($parts);

        return lcfirst($shortName);
    }

    public function hasFilter(string $nameOrClass): bool
    {
        return null !== $this->getFilter($nameOrClass);
    }
}
