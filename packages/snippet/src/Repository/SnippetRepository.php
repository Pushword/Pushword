<?php

namespace Pushword\Snippet\Repository;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Snippet\Entity\Snippet;

/**
 * @extends ServiceEntityRepository<Snippet>
 */
#[AsDoctrineListener(event: Events::onClear)]
class SnippetRepository extends ServiceEntityRepository
{
    /** @var array<string, array<string, Snippet>> host => [slug => Snippet] */
    private array $slugCache = [];

    /** @var array<string, true> */
    private array $warmedHosts = [];

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
     *
     * Snippets are render-hot (every `snippet()` Twig call), so each host is
     * loaded into an in-memory slug cache on first lookup: a page using N
     * snippets costs one query for its host (plus one for the global fallback),
     * not up to 2N. The cache is dropped on EntityManager::clear().
     */
    public function findOneBySlugForHost(string $slug, string $host): ?Snippet
    {
        $slug = Snippet::normalizeSlug($slug);

        $this->warmupHost($host);
        $snippet = $this->slugCache[$host][$slug] ?? null;

        if (null !== $snippet || '' === $host) {
            return $snippet;
        }

        $this->warmupHost('');

        return $this->slugCache[''][$slug] ?? null;
    }

    /**
     * Load every snippet of a host into the slug cache with a single query.
     */
    private function warmupHost(string $host): void
    {
        if (isset($this->warmedHosts[$host])) {
            return;
        }

        $this->slugCache[$host] = [];
        foreach ($this->findByHost($host) as $snippet) {
            $this->slugCache[$host][$snippet->getSlug()] = $snippet;
        }

        $this->warmedHosts[$host] = true;
    }

    /**
     * Drop the slug cache. Called automatically when EntityManager::clear() is
     * invoked so CLI batch paths don't serve detached entities.
     */
    public function onClear(): void
    {
        $this->slugCache = [];
        $this->warmedHosts = [];
    }

    /**
     * @return Snippet[]
     */
    public function findByHost(string $host): array
    {
        return $this->findBy(['host' => $host], ['slug' => 'ASC']);
    }
}
