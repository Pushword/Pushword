<?php

namespace Pushword\Core\Component\EntityFilter;

use Exception;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\EntityFilter\Filter\FilterInterface;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesInterface;
use Pushword\Core\Utils\F;
use ReflectionClass;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment as Twig;

/**
 * @template T of object
 */
final class Manager
{
    /**
     * @var T
     */
    private object $entity;

    private AppConfig $app;

    private AppPool $apps;

    /**
     * @var ManagerPool<T>
     */
    private ManagerPool $managerPool;

    private Twig $twig;

    private EventDispatcherInterface $eventDispatcher;

    /** @param T $entity
     * @param ManagerPool<T> $managerPool
     */
    public function __construct(
        ManagerPool $managerPool,
        EventDispatcherInterface $eventDispatcher,
        object $entity
    ) {
        $this->managerPool = $managerPool;
        $this->apps = $managerPool->apps;
        $this->twig = $managerPool->twig;
        $this->entity = $entity;
        $this->eventDispatcher = $eventDispatcher;
        $this->app = method_exists($entity, 'getHost') ? $this->apps->get($entity->getHost()) : $this->apps->get();
    }

    /**
     * @return T
     */
    public function getEntity(): object
    {
        return $this->entity;
    }

    /**
     * Magic getter for Entity properties.
     *
     * @param array<mixed> $arguments
     *
     * @return mixed
     */
    public function __call(string $method, array $arguments = [])
    {
        if (\Safe\preg_match('/^get/', $method) < 1) {
            $method = 'get'.ucfirst($method);
        }

        $filterEvent = new FilterEvent($this, \Safe\substr($method, 3));
        $this->eventDispatcher->dispatch($filterEvent, FilterEvent::NAME_BEFORE);

        $returnValue = [] !== $arguments ? \call_user_func_array([$this->entity, $method], $arguments) // @phpstan-ignore-line
            : \call_user_func([$this->entity, $method]);    // @phpstan-ignore-line

        $returnValue = $this->filter(\Safe\substr($method, 3), $returnValue);

        $this->eventDispatcher->dispatch($filterEvent, FilterEvent::NAME_AFTER);

        return $returnValue;
    }

    /**
     * main_content => apply filters on mainContent (*_filters => camelCase(*))
     * string       => apply filters on each string property.
     *
     * @param mixed $propertyValue
     *
     * @return mixed
     */
    private function filter(string $property, $propertyValue)
    {
        $filters = $this->getFilters($this->camelCaseToSnakeCase($property));

        if ([] === $filters && \is_string($propertyValue)) {
            $filters = $this->getFilters('string');
        }

        return [] !== $filters
            ? $this->applyFilters($property,  '' !== \strval($propertyValue) ? $propertyValue : '', $filters)
            : $propertyValue;
    }

    private function camelCaseToSnakeCase(string $string): string
    {
        return strtolower(F::preg_replace_str('/[A-Z]/', '_\\0', lcfirst($string)));
    }

    /** @return string[] */
    private function getFilters(string $label): array
    {
        if ($this->app->entityCanOverrideFilters() && $this->entity instanceof CustomPropertiesInterface) {
            $filters = $this->entity->getCustomProperty($label.'_filters');
        }

        if (! isset($filters) || \in_array($filters, [[], '', null], true)) {
            $appFilters = $this->app->getFilters();
            $filters = isset($appFilters[$label]) ? $appFilters[$label] : null;
        }

        $filters = \is_string($filters) ? explode(',', $filters) : $filters;

        return $filters ? $filters : []; // @phpstan-ignore-line
    }

    /**
     * @noRector
     *
     * @return false|class-string
     */
    private function isFilter(string $className)
    {
        $filterClass = class_exists($className) ? $className
            : 'Pushword\Core\Component\EntityFilter\Filter\\'.ucfirst($className);

        if (! class_exists($filterClass)) {
            return false;
        }

        $reflectionClass = new ReflectionClass($filterClass);
        if (! $reflectionClass->implementsInterface(FilterInterface::class)) {
            return false;
        }

        return $filterClass;
    }

    private function getFilterClass(string $filter): FilterInterface
    {
        if (false === ($filterClassName = $this->isFilter($filter))) {
            throw new Exception('Filter `'.$filter.'` not found');
        }

        $filterClass = new $filterClassName();

        // Some kind of autoload ... move it to real autoload
        if (method_exists($filterClass, 'setEntity')) {
            $filterClass->setEntity($this->entity);
        }

        if (method_exists($filterClass, 'setApp')) {
            $filterClass->setApp($this->app);
        }

        if (method_exists($filterClass, 'setTwig')) {
            $filterClass->setTwig($this->twig);
        }

        if (method_exists($filterClass, 'setManager')) {
            $filterClass->setManager($this);
        }

        if (method_exists($filterClass, 'setManagerPool')) {
            $filterClass->setManagerPool($this->managerPool);
        }

        return $filterClass; // @phpstan-ignore-line
    }

    /**
     * @param mixed    $propertyValue
     * @param string[] $filters
     *
     * @return mixed
     */
    private function applyFilters(string $property, $propertyValue, array $filters)
    {
        foreach ($filters as $filter) {
            if ($this->entity instanceof CustomPropertiesInterface
                && \in_array($this->entity->getCustomProperty('filter_'.$this->className($filter)), [0, false], true)) {
                continue;
            }

            $filterClass = $this->getFilterClass($filter);

            if (method_exists($filterClass, 'setProperty')) {
                $filterClass->setProperty($property);
            }

            $propertyValue = $filterClass->apply($propertyValue);
        }

        return $propertyValue;
    }

    private function className(string $name): string
    {
        $name = \Safe\substr($name, (int) strrpos($name, '/'));

        return lcfirst($name);
    }
}
