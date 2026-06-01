<?php

namespace Pushword\PageWorkflow\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Core\Entity\Page;
use Pushword\PageWorkflow\Entity\PageEditorialState;

/**
 * @extends ServiceEntityRepository<PageEditorialState>
 */
class PageEditorialStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageEditorialState::class);
    }

    public function findOrCreateFor(Page $page): PageEditorialState
    {
        $state = $this->findOneBy(['page' => $page]);
        if (null !== $state) {
            return $state;
        }

        $state = new PageEditorialState($page);
        $this->getEntityManager()->persist($state);

        return $state;
    }

    public function findFor(Page $page): ?PageEditorialState
    {
        return $this->findOneBy(['page' => $page]);
    }
}
