<?php

namespace Pushword\Core\Content;

use Exception;
use LogicException;
use Pushword\Core\Component\EntityFilter\FilterEvent;
use Pushword\Core\Component\EntityFilter\FilterRegistry;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Utils\Entity;

use function Safe\preg_match;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ContentPipeline
{
    private readonly SiteConfig $site;

    private ?Manager $legacyManager = null;

    /** @var array<string, mixed> */
    private array $propertyCache = [];

    /** @var array<string, string> */
    private static array $snakeCaseCache = [];

    public function __construct(
        private readonly ContentPipelineFactory $factory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterRegistry $filterRegistry,
        public readonly Page $page,
        SiteRegistry $apps,
    ) {
        $this->site = $apps->get($page->host);
    }

    public function getMainContent(): string
    {
        $value = $this->getFilteredProperty('MainContent');
        assert(\is_string($value));

        return $value;
    }

    public function getTitle(): string
    {
        $value = $this->getFilteredProperty('Title');
        assert(\is_string($value));

        return $value;
    }

    public function getName(): string
    {
        $value = $this->getFilteredProperty('Name');
        assert(\is_string($value));

        return $value;
    }

    /**
     * @param array<mixed> $arguments
     */
    public function getFilteredProperty(string $property, array $arguments = []): mixed
    {
        $cacheKey = [] !== $arguments ? $property.':'.hash('xxh3', (string) json_encode($arguments)) : $property;

        if (isset($this->propertyCache[$cacheKey])) {
            return $this->propertyCache[$cacheKey];
        }

        $filterEvent = new FilterEvent($this->getLegacyManager(), $property);
        $this->eventDispatcher->dispatch($filterEvent, FilterEvent::NAME_BEFORE);

        $returnValue = $this->readProperty($property, $arguments);

        if (! \is_scalar($returnValue)) {
            throw new LogicException('Property '.$property.' must return a scalar value');
        }

        $returnValue = $this->filter($property, $returnValue);

        $this->eventDispatcher->dispatch($filterEvent, FilterEvent::NAME_AFTER);

        $this->propertyCache[$cacheKey] = $returnValue;

        return $returnValue;
    }

    /**
     * Page carries its columns as PHP 8.4 hooked properties and keeps a getter only
     * where reading does more than return the value — `getMainContent()` (protected
     * column), `getTemplate()` (falls back to the extended page). A real method wins
     * over the property it wraps, and `Page::__call()` answers what is neither, from
     * `customProperties`. The test has to be `method_exists()`: `is_callable()` is
     * true for every `getX` on a class that declares `__call()`.
     *
     * @param array<mixed> $arguments
     */
    private function readProperty(string $property, array $arguments): mixed
    {
        $method = 'get'.$property;
        $name = lcfirst($property);

        if ([] === $arguments
            && ! method_exists($this->page, $method)
            && Entity::isPubliclyReadableProperty($this->page, $name)) {
            return $this->page->{$name}; // @phpstan-ignore property.dynamicName
        }

        return $this->page->{$method}(...$arguments); // @phpstan-ignore method.dynamicName
    }

    /**
     * The pipeline of another page, to resolve a property against it — what the
     * `Extended` filter walks when a page inherits an empty field.
     */
    public function for(Page $page): self
    {
        return $this->factory->get($page);
    }

    /**
     * Filters still receive the legacy Manager, which is a facade over this pipeline.
     */
    public function getLegacyManager(): Manager
    {
        return $this->legacyManager ??= new Manager($this);
    }

    /**
     * Magic getter for backward compatibility.
     *
     * @param array<mixed> $arguments
     */
    public function __call(string $method, array $arguments = []): mixed
    {
        if (preg_match('/^get/', $method) < 1) {
            $method = 'get'.ucfirst($method);
        }

        return $this->getFilteredProperty(substr($method, 3), $arguments);
    }

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

    /** @return string[] */
    private function getFilters(string $label): array
    {
        $filters = null;

        if ($this->site->entityCanOverrideFilters) {
            $filters = $this->page->getCustomProperty($label.'_filters');
        }

        if (null === $filters || '' === $filters) {
            $filters = $this->site->filters[$label] ?? null;
        }

        if (\is_string($filters)) {
            return explode(',', $filters);
        }

        if (! \is_array($filters)) {
            return [];
        }

        return array_map(static fn (mixed $item): string => \is_scalar($item) ? (string) $item : throw new Exception(), $filters);
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

            $propertyValue = $filterInstance->apply($propertyValue, $this->page, $this->getLegacyManager(), $property);
        }

        return $propertyValue;
    }

    private function className(string $name): string
    {
        $name = substr($name, (int) strrpos($name, '/'));

        return lcfirst($name);
    }
}
