<?php

namespace Pushword\Core\Component\EntityFilter;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Router\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment as Twig;

/**
 * @template T of object
 *
 * @implements ManagerPoolInterface<T>
 */
final class ManagerPool implements ManagerPoolInterface
{
    /** @required */
    public AppPool $apps;

    /** @required */
    public Twig $twig;

    /** @required */
    public EventDispatcherInterface $eventDispatcher;

    /** @required */
    public RouterInterface $router;

    /** @required */
    public EntityManagerInterface $entityManager;

    /** @var array<(string|int), Manager<T>> */
    private array $entityFilterManagers = [];

    /**
     * @return Manager<T>
     *
     * @psalm-suppress InvalidArgument
     */
    public function getManager(IdInterface $id): Manager
    {
        if (null !== $id->getId() && isset($this->entityFilterManagers[$id->getId()])) {
            return $this->entityFilterManagers[$id->getId()];
        }

        $this->entityFilterManagers[$id->getId()] = new Manager($this, $this->eventDispatcher, $id); // @phpstan-ignore-line

        return $this->entityFilterManagers[$id->getId()]; // @phpstan-ignore-line
    }

    /**
     * @return mixed|\Pushword\Core\Component\EntityFilter\Manager
     */
    public function getProperty(IdInterface $id, string $property = '')
    {
        $manager = $this->getManager($id);

        if ('' === $property) {
            return $manager;
        }

        if (! method_exists($manager, $property)) {
            throw new Exception('Property `'.$property.'` doesn\'t exist');
        }

        return $manager->$property(); // @phpstan-ignore-line
    }
}
