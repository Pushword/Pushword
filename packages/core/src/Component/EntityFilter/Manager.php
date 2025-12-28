<?php

namespace Pushword\Core\Component\EntityFilter;

use Exception;
use LogicException;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;

use function Safe\preg_match;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @method string getMainContent()
 */
final class Manager
{
    private readonly AppConfig $app;

    private readonly AppPool $apps;

    /** @var array<string, mixed> */
    private array $propertyCache = [];

    /** @var array<string, string> */
    private static array $snakeCaseCache = [];

    public function __construct(
        private readonly ManagerPool $managerPool,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterRegistry $filterRegistry,
        public readonly Page $page,
    ) {
        $this->apps = $managerPool->apps;
        $this->app = $this->apps->get($page->host);
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getManagerPool(): ManagerPool
    {
        return $this->managerPool;
    }

    /**
     * Magic getter for Entity properties.
     *
     * @param array<mixed> $arguments
     */
    public function __call(string $method, array $arguments = []): mixed
    {
        if (preg_match('/^get/', $method) < 1) {
            $method = 'get'.ucfirst($method);
        }

        $property = substr($method, 3);
        $cacheKey = [] !== $arguments ? $property.':'.hash('xxh3', (string) json_encode($arguments)) : $property;

        if (isset($this->propertyCache[$cacheKey])) {
            return $this->propertyCache[$cacheKey];
        }

        $filterEvent = new FilterEvent($this, $property);
        $this->eventDispatcher->dispatch($filterEvent, FilterEvent::NAME_BEFORE);

        if (! \is_callable($pageMethod = [$this->page, $method])) {
            throw new LogicException();
        }

        $returnValue = [] !== $arguments ? \call_user_func_array($pageMethod, $arguments) : \call_user_func($pageMethod);

        if (! \is_scalar($returnValue)) {
            throw new LogicException();
        }

        $returnValue = $this->filter($property, $returnValue);

        $this->eventDispatcher->dispatch($filterEvent, FilterEvent::NAME_AFTER);

        $this->propertyCache[$cacheKey] = $returnValue;

        return $returnValue;
    }

    /**
     * main_content => apply filters on mainContent (*_filters => camelCase(*))
     * string       => apply filters on each string property.
     */
    private function filter(string $property, bool|float|int|string|null $propertyValue): mixed
    {
        $filters = $this->getFilters($this->camelCaseToSnakeCase($property));

        if ([] === $filters && \is_string($propertyValue)) {
            $filters = $this->getFilters('string');
        }

        return [] !== $filters
            ? $this->applyFilters('' !== (string) $propertyValue ? $propertyValue : '', $filters, $property)
            : $propertyValue;
    }

    private function camelCaseToSnakeCase(string $string): string
    {
        return self::$snakeCaseCache[$string] ??= strtolower(
            preg_replace('/[A-Z]/', '_\\0', lcfirst($string)) ?? throw new Exception()
        );
    }

    /**
     * @return string[]
     */
    private function getFilters(string $label): array
    {
        if ($this->app->entityCanOverrideFilters()) {
            $filters = $this->page->getCustomProperty($label.'_filters');
        }

        if (! isset($filters) || \is_string($filters) && \in_array($filters, [[], '', null], true)) {
            $appFilters = $this->app->getFilters();
            $filters = $appFilters[$label] ?? null;
        }

        if (is_string($filters)) {
            return explode(',', $filters);
        }

        if (! is_array($filters)) {
            return [];
        }

        // Ensure all elements are strings
        return array_map(fn ($item): string => is_scalar($item) ? (string) $item : throw new Exception(), $filters);
    }

    /**
     * @param string[] $filters
     */
    public function applyFilters(bool|float|int|string|null $propertyValue, array $filters, string $property = ''): mixed
    {
        foreach ($filters as $filter) {
            if (\in_array($this->page->getCustomProperty('filter_'.$this->className($filter)), [0, false], true)) {
                continue;
            }

            $filterInstance = $this->filterRegistry->getFilter($filter);

            if (null === $filterInstance) {
                throw new Exception('Filter `'.$filter.'` not found');
            }

            $propertyValue = $filterInstance->apply($propertyValue, $this->page, $this, $property);
        }

        return $propertyValue;
    }

    private function className(string $name): string
    {
        $name = substr($name, (int) strrpos($name, '/'));

        return lcfirst($name);
    }
}
