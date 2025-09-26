<?php

namespace Pushword\Core\Component\EntityFilter;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Service\LinkProvider;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

final class ManagerPool
{
    public function __construct(
        public AppPool $apps,
        public Twig $twig,
        public EventDispatcherInterface $eventDispatcher,
        public PushwordRouteGenerator $router,
        public LinkProvider $linkProvider,
        public EntityManagerInterface $entityManager
    ) {
    }

    /** @var array<(string|int), Manager> */
    private array $entityFilterManagers = [];

    public function getManager(Page $page): Manager
    {
        $id = $page->getId() ?? 0;

        if (isset($this->entityFilterManagers[$id])) {
            return $this->entityFilterManagers[$id];
        }

        $this->entityFilterManagers[$id] = new Manager($this, $this->eventDispatcher, $this->linkProvider, $page);

        return $this->entityFilterManagers[$id];
    }

    /**
     * @return mixed|Manager
     */
    #[AsTwigFunction('pw')]
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
