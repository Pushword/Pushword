<?php

namespace Pushword\Core\Component\EntityFilter;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\EntityFilter\Filter\FilterInterface;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesInterface;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Utils\F;

use function Safe\preg_match;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment as Twig;

/**
 * @template T of object
 */
final readonly class Manager
{
    private AppConfig $app;

    private AppPool $apps;

    private Twig $twig;

    private PushwordRouteGenerator $router;

    private EntityManagerInterface $entityManager;

    /** @param T $entity
     * @param ManagerPool<T> $managerPool
     */
    public function __construct(
        private ManagerPool $managerPool,
        private EventDispatcherInterface $eventDispatcher,
        private object $entity
    ) {
        $this->apps = $managerPool->apps;
        $this->twig = $managerPool->twig;
        $this->router = $managerPool->router;
        $this->entityManager = $managerPool->entityManager;
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
     */
    public function __call(string $method, array $arguments = []): mixed
    {
        if (preg_match('/^get/', $method) < 1) {
            $method = 'get'.ucfirst($method);
        }

        $filterEvent = new FilterEvent($this, substr($method, 3));
        $this->eventDispatcher->dispatch($filterEvent, FilterEvent::NAME_BEFORE);

        $returnValue = [] !== $arguments ? \call_user_func_array([$this->entity, $method], $arguments) // @phpstan-ignore-line
            : \call_user_func([$this->entity, $method]);    // @phpstan-ignore-line

        if (! \is_scalar($returnValue)) {
            throw new \LogicException();
        }

        $returnValue = $this->filter(substr($method, 3), $returnValue);

        $this->eventDispatcher->dispatch($filterEvent, FilterEvent::NAME_AFTER);

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
            $filters = $appFilters[$label] ?? null;
        }

        $filters = \is_string($filters) ? explode(',', $filters) : $filters;

        return $filters ?: []; // @phpstan-ignore-line
    }

    /**
     * @noRector
     *
     * @return false|class-string
     */
    private function isFilter(string $className): false|string
    {
        $filterClass = class_exists($className) ? $className
            : 'Pushword\Core\Component\EntityFilter\Filter\\'.ucfirst($className);

        if (! class_exists($filterClass)) {
            return false;
        }

        $reflectionClass = new \ReflectionClass($filterClass);
        if (! $reflectionClass->implementsInterface(FilterInterface::class)) {
            return false;
        }

        return $filterClass;
    }

    private function getFilterClass(string $filter): FilterInterface
    {
        if (false === ($filterClassName = $this->isFilter($filter))) {
            throw new \Exception('Filter `'.$filter.'` not found');
        }

        $filterClass = new $filterClassName();

        $toCheck = [
            'setEntity' => 'entity',
            'setApp' => 'app',
            'setApps' => 'apps',
            'setTwig' => 'twig',
            'setManager' => '',
            'setManagerPool' => 'managerPool',
            'setRouter' => 'router',
            'setEntityManager' => 'entityManager',
        ];

        foreach ($toCheck as $method => $property) {
            if (method_exists($filterClass, $method)) {
                $filterClass->$method('' === $property ? $this : $this->$property); // @phpstan-ignore-line
            }
        }

        return $filterClass; // @phpstan-ignore-line
    }

    /**
     * @param string[] $filters
     */
    private function applyFilters(string $property, bool|float|int|string|null $propertyValue, array $filters): mixed
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
        $name = substr($name, (int) strrpos($name, '/'));

        return lcfirst($name);
    }
}
