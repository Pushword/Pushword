<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Cache;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\RevisionCalculator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

/** Coordinates cache invalidation with the final writes of concurrent renders. */
final readonly class PageCachePublication
{
    private LockFactory $locks;

    public function __construct(
        private RevisionCalculator $revisions,
        private EntityManagerInterface $entityManager,
        string $varDir,
    ) {
        $directory = $varDir.'/page-cache-publication';
        new Filesystem()->mkdir($directory);
        $this->locks = new LockFactory(new FlockStore($directory));
    }

    public function revision(Page $page): string
    {
        // The API revision covers columns; foreign keys can also change a render,
        // including when two edits share the same second in updatedAt.
        return hash('xxh128', json_encode([
            $this->revisions->compute($page),
            $page->mainImage?->id,
            $page->parentPage?->id,
            $page->extendedPage?->id,
            $page->variantOf?->id,
            $page->createdBy?->id,
            $page->editedBy?->id,
        ], \JSON_THROW_ON_ERROR));
    }

    /** @param callable(): void $removeFiles */
    public function invalidate(Page $page, callable $removeFiles): void
    {
        $this->synchronized($page, $removeFiles);
    }

    /** @param callable(): void $writeFiles */
    public function publish(Page $page, string $revision, callable $writeFiles): void
    {
        $this->synchronized($page, function () use ($page, $revision, $writeFiles): void {
            // Bypass the identity map: a long-lived worker may hold an old snapshot.
            $current = $this->entityManager->createQueryBuilder()
                ->select('p')
                ->from(Page::class, 'p')
                ->where('p.id = :id')
                ->setParameter('id', $page->id)
                ->getQuery()
                ->setHint(Query::HINT_REFRESH, true)
                ->getOneOrNullResult();

            if (! $current instanceof Page || $this->revision($current) !== $revision) {
                return;
            }

            $writeFiles();
        });
    }

    /** @param callable(): void $operation */
    private function synchronized(Page $page, callable $operation): void
    {
        $key = hash('xxh128', $page->host."\0".$page->getRealSlug());
        $lock = $this->locks->createLock($key);
        $lock->acquire(true);

        try {
            $operation();
        } finally {
            $lock->release();
        }
    }
}
