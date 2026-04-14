<?php

namespace Pushword\Core\Component\EntityFilter;

use Exception;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ManagerPool
{
    public function __construct(
        public SiteRegistry $apps,
        public EventDispatcherInterface $eventDispatcher,
        private readonly FilterRegistry $filterRegistry,
    ) {
    }

    /** @var array<(string|int), Manager> */
    private array $entityFilterManagers = [];

    public function getManager(Page $page): Manager
    {
        $id = $page->id ?? 'obj_'.spl_object_id($page);

        if (isset($this->entityFilterManagers[$id])) {
            return $this->entityFilterManagers[$id];
        }

        $this->entityFilterManagers[$id] = new Manager(
            $this,
            $this->eventDispatcher,
            $this->filterRegistry,
            $page
        );

        return $this->entityFilterManagers[$id];
    }

    /**
     * @return mixed|Manager
     */
    public function getProperty(Page $page, string $property = ''): mixed
    {
        $manager = $this->getManager($page);

        if ('' === $property) {
            return $manager;
        }

        if (! method_exists($manager, $property)) {
            throw new Exception('Property `'.$property."` doesn't exist");
        }

        return $manager->$property(); // @phpstan-ignore-line
    }
}
