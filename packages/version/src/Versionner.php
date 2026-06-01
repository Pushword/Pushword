<?php

namespace Pushword\Version;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\EventListener\PageListener;
use Pushword\Core\Service\RevisionCalculator;
use Pushword\Snippet\Entity\Snippet;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class Versionner
{
    private readonly Filesystem $fileSystem;

    public static bool $version = true;

    public function __construct(
        private readonly string $storageDir,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly RevisionCalculator $revisions,
    ) {
        $this->fileSystem = new Filesystem();
    }

    /**
     * URL type slug => versionable entity class. Snippet is included only when
     * the optional snippet bundle is installed.
     *
     * @return array<string, class-string>
     */
    public static function versionableTypes(): array
    {
        $types = ['page' => Page::class];

        if (class_exists(Snippet::class)) {
            $types['snippet'] = Snippet::class;
        }

        return $types;
    }

    public function postPersist(PostPersistEventArgs $lifecycleEventArgs): void
    {
        $this->postUpdate($lifecycleEventArgs);
    }

    public function postUpdate(PostPersistEventArgs|PostUpdateEventArgs $lifecycleEventArgs): void
    {
        if (! static::$version) {
            return;
        }

        $entity = $lifecycleEventArgs->getObject();
        $type = $this->typeOf($entity);

        if (null === $type) {
            return;
        }

        \assert($entity instanceof IdInterface); // every versionable type uses IdTrait
        $this->createVersion($type, $entity);
    }

    private function typeOf(object $entity): ?string
    {
        foreach (self::versionableTypes() as $type => $class) {
            if ($entity instanceof $class) {
                return $type;
            }
        }

        return null;
    }

    private function createVersion(string $type, IdInterface $entity): void
    {
        $id = (int) $entity->id;
        $jsonContent = $this->revisions->serialize($entity);
        $hash = sha1($jsonContent);

        // Idempotency: if the most recent snapshot already carries this hash,
        // the entity's column-mapped state did not change. Skip writing a
        // duplicate (e.g. flushes triggered by listeners that don't actually
        // change a column).
        $latest = $this->getLatestVersion($type, $id);
        if (null !== $latest && str_ends_with($latest, '_'.$hash)) {
            return;
        }

        $this->fileSystem->dumpFile(
            $this->getVersionFile($type, $id, $this->buildVersionFilename($hash)),
            $jsonContent,
        );

        $this->pruneOldVersions($type, $id);
    }

    /**
     * Versionner filenames keep a `uniqid()` time prefix so they stay
     * chronologically sortable (used by getLatestVersion / pruning), and
     * append the content hash so the file embeds the revision token the
     * API and flat exporter advertise. Format: `<sec><usec>_<sha1>`.
     */
    private function buildVersionFilename(string $hash): string
    {
        return uniqid().'_'.$hash;
    }

    /**
     * Cap history growth with a tiered policy (timestamps come from the
     * filename, no extra metadata): keep every version younger than 30 days,
     * then one per day up to 90 days, then one per week beyond.
     */
    private function pruneOldVersions(string $type, int|string $id): void
    {
        $now = time();
        $keep = []; // bucket => newest filename kept in that bucket
        $toDelete = [];

        foreach ($this->getVersions($type, $id) as $version) {
            $timestamp = $this->timestampOf($version);
            if (null === $timestamp) {
                continue; // never prune names we cannot date
            }

            $age = $now - $timestamp;
            if ($age < 30 * 86400) {
                continue; // keep all recent versions
            }

            $bucket = $age < 90 * 86400
                ? 'd'.intdiv($timestamp, 86400)     // 1/day in the 30–90 days window
                : 'w'.intdiv($timestamp, 7 * 86400); // 1/week beyond 90 days

            if (! isset($keep[$bucket])) {
                $keep[$bucket] = $version;

                continue;
            }

            // uniqid filenames sort chronologically, so keep the greater one.
            if ($version > $keep[$bucket]) {
                $toDelete[] = $keep[$bucket];
                $keep[$bucket] = $version;
            } else {
                $toDelete[] = $version;
            }
        }

        foreach ($toDelete as $version) {
            $this->fileSystem->remove($this->getVersionFile($type, $id, $version));
        }
    }

    private function timestampOf(string $version): ?int
    {
        $hex = substr($version, 0, 8);

        return ctype_xdigit($hex) ? (int) hexdec($hex) : null;
    }

    public function loadVersion(string $type, string $id, string $version): void
    {
        static::$version = false;
        PageListener::$skipSlugChangeDetection = true;

        try {
            $entity = $this->find($type, $id);

            if ($entity instanceof Page) {
                $this->loadPageVersion($entity, $type, $id, $version);
            } else {
                $this->populate($entity, $type, $id, $version);
            }

            $this->entityManager->flush();
        } finally {
            static::$version = true;
            PageListener::$skipSlugChangeDetection = false;
        }
    }

    private function loadPageVersion(Page $page, string $type, string $id, string $version): void
    {
        $oldSlug = $page->slug;
        $this->populate($page, $type, $id, $version);

        // If slug changed, remove any redirect page that would conflict
        if ($page->slug !== $oldSlug) {
            $conflicting = $this->entityManager->getRepository(Page::class)->findOneBy([
                'slug' => $page->slug,
                'host' => $page->host,
            ]);
            if (null !== $conflicting && $conflicting->id !== $page->id && null !== $conflicting->getRedirection()) {
                $this->entityManager->remove($conflicting);
            }
        }
    }

    public function populate(object $entity, string $type, int|string $id, string $version): object
    {
        $serialized = $this->fileSystem->readFile($this->getVersionFile($type, $id, $version));

        $this->serializer->deserialize($serialized, $entity::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $entity,
            ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true, // permits to import tags as string
        ]);

        return $entity;
    }

    public function find(string $type, int|string $id): object
    {
        $class = self::versionableTypes()[$type] ?? throw new Exception('Unknown version type `'.$type.'`');

        $entity = $this->entityManager->getRepository($class)->find($id);

        if (null === $entity) {
            throw new Exception($type.' not found `'.$id.'`');
        }

        return $entity;
    }

    public function reset(string $type, int|string $id): void
    {
        $this->fileSystem->remove($this->getVersionDir($type, $id));
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * @return string[]
     */
    public function getVersions(string $type, int|string $id): array
    {
        $dir = $this->getVersionDir($type, $id);
        if (! $this->fileSystem->exists($dir)) {
            return [];
        }

        /** @var string[] */
        $scandir = scandir($dir);

        $versions = array_filter($scandir, static fn (string $item): bool => ! \in_array($item, ['.', '..'], true));

        return array_values($versions);
    }

    public function getLatestVersion(string $type, int|string $id): ?string
    {
        $versions = $this->getVersions($type, $id);
        if ([] === $versions) {
            return null;
        }

        sort($versions); // uniqid prefix is chronological

        return $versions[array_key_last($versions)];
    }

    private function getVersionDir(string $type, int|string $id): string
    {
        // Page versions keep their historical flat path; other types are
        // namespaced by their slug so ids cannot collide across entities.
        $prefix = 'page' === $type ? '' : $type.'/';

        return $this->storageDir.'/'.$prefix.$id;
    }

    private function getVersionFile(string $type, int|string $id, string $version): string
    {
        return $this->getVersionDir($type, $id).'/'.$version;
    }
}
