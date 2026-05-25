<?php

namespace Pushword\Snippet\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Snippet\Entity\Snippet;

/**
 * @extends ServiceEntityRepository<Snippet>
 */
class SnippetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Snippet::class);
    }

    public function findOneBySlugAndHost(string $slug, string $host): ?Snippet
    {
        return $this->findOneBy(['slug' => Snippet::normalizeSlug($slug), 'host' => $host]);
    }

    /**
     * Resolve a snippet for a host, preferring an exact host match over a
     * global (host-less, `host = ''`) snippet that applies to every host.
     */
    public function findOneBySlugForHost(string $slug, string $host): ?Snippet
    {
        $slug = Snippet::normalizeSlug($slug);

        $snippet = $this->findOneBy(['slug' => $slug, 'host' => $host]);

        if (null !== $snippet || '' === $host) {
            return $snippet;
        }

        return $this->findOneBy(['slug' => $slug, 'host' => '']);
    }

    /**
     * @return Snippet[]
     */
    public function findByHost(string $host): array
    {
        return $this->findBy(['host' => $host], ['slug' => 'ASC']);
    }
}
