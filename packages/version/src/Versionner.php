<?php

declare(strict_types=1);

namespace Pushword\Version;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Column;
use Exception;
use Pushword\Core\Collaboration\LiveWrite;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\EventListener\PageListener;
use Pushword\Core\Service\RevisionCalculator;
use Pushword\Core\Utils\Entity;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Version\Entity\VersionLog;
use Stringable;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use UnexpectedValueException;

/**
 * @phpstan-type SnapshotMetadata array{h1?: string, title?: string, name?: string, slug?: string, editMessage?: string|null, holdPublicationAt?: string|null, editedBy?: array{username?: string}}
 */
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
        private readonly ?LiveWrite $liveWrite = null,
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
        $this->handle($lifecycleEventArgs->getObject(), VersionLog::ACTION_CREATED);
    }

    public function postUpdate(PostUpdateEventArgs $lifecycleEventArgs): void
    {
        $this->handle($lifecycleEventArgs->getObject(), VersionLog::ACTION_UPDATED);
    }

    private function handle(object $entity, string $action): void
    {
        if (! static::$version) {
            return;
        }

        $type = $this->typeOf($entity);

        if (null === $type) {
            return;
        }

        \assert($entity instanceof IdInterface); // every versionable type uses IdTrait
        if (true === $this->liveWrite?->isActive($entity)) {
            $previous = $this->liveWrite->previousPage();
            if (null !== $previous) {
                [$content, $editor] = $previous;
                $this->createVersion($type, $entity, $action, $content, $editor);
            }

            return;
        }

        $editor = $entity instanceof Page ? $entity->editedBy?->getUserIdentifier() : null;
        $this->createVersion($type, $entity, $action, $this->revisions->serialize($entity), $editor);
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

    private function createVersion(string $type, IdInterface $entity, string $action, string $jsonContent, ?string $editor): void
    {
        $id = (int) $entity->id;
        $hash = sha1($jsonContent);

        // Idempotency: if the most recent snapshot already carries this hash,
        // the entity's column-mapped state did not change. Skip writing a
        // duplicate (e.g. flushes triggered by listeners that don't actually
        // change a column).
        $latest = $this->getLatestVersion($type, $id);
        if (null !== $latest && str_ends_with($latest, '_'.$hash)) {
            return;
        }

        $filename = $this->buildVersionFilename($hash);
        $snapshot = $this->snapshotWithEditor($jsonContent, $editor);
        $this->fileSystem->dumpFile(
            $this->getVersionFile($type, $id, $filename),
            gzencode($snapshot, 1) ?: $snapshot,
        );

        $this->pruneOldVersions($type, $id);

        $this->logActivity($type, $entity, $filename, $action, $editor);
    }

    /**
     * Append a row to the queryable activity index (version_log). Uses a direct
     * DBAL insert instead of persisting an entity so it is safe to call from
     * within a Doctrine flush (postPersist/postUpdate) without mutating the
     * UnitOfWork. Title/host are denormalized so the admin journal renders
     * without reopening snapshot files or hydrating the entity.
     */
    public function logActivity(string $type, IdInterface $entity, ?string $version, string $action, ?string $editor): void
    {
        $this->entityManager->getConnection()->insert('version_log', [
            'type' => $type,
            'entity_id' => (int) $entity->id,
            'version' => $version,
            'action' => $action,
            'editor' => $editor,
            // Cut: a page's label falls back to its TEXT title, and MySQL refuses an
            // over-long row rather than truncating it — taking the page write with it.
            'title' => mb_substr($this->labelOf($entity), 0, VersionLog::TITLE_MAX_LENGTH),
            'slug' => $this->slugOf($entity),
            'host' => $this->hostOf($entity),
            'created_at' => new DateTimeImmutable(),
        ], ['created_at' => Types::DATETIME_IMMUTABLE]);
    }

    private function labelOf(IdInterface $entity): string
    {
        if ($entity instanceof Page) {
            return $entity->h1 ?: ($entity->title ?: $entity->slug);
        }

        // Snippet (the only other versionable type) is Stringable: name ?: slug.
        return $entity instanceof Stringable ? (string) $entity : (string) $entity->id;
    }

    private function hostOf(IdInterface $entity): ?string
    {
        // Both versionable types carry a host via HostTrait, but Snippet does not
        // declare HostInterface, so match the concrete types like labelOf().
        return $entity instanceof Page || $entity instanceof Snippet ? $entity->host : null;
    }

    private function slugOf(IdInterface $entity): ?string
    {
        return match (true) {
            $entity instanceof Page, $entity instanceof Snippet => $entity->slug,
            default => null,
        };
    }

    /**
     * The hash-backed snapshot only covers `#[Column]` properties, so the
     * editor (a ManyToOne association) is absent. Inject it for display in the
     * version list without touching the revision hash. populate() restricts
     * deserialization to column properties, so this extra key is ignored on
     * restore (no phantom User is created). Flat imports have no authenticated
     * editor, so editedBy stays null and nothing is injected.
     */
    private function snapshotWithEditor(string $jsonContent, ?string $editor): string
    {
        if (null === $editor) {
            return $jsonContent;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($jsonContent, true, 512, \JSON_THROW_ON_ERROR);
        $data['editedBy'] = ['username' => $editor];

        return json_encode($data, \JSON_THROW_ON_ERROR);
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
        $this->serializer->deserialize($this->readSnapshot($type, $id, $version), $entity::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $entity,
            ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true, // permits to import tags as string
            // Restrict to the same column properties the snapshot was built
            // from, so display-only keys like editedBy never reach the entity.
            AbstractNormalizer::ATTRIBUTES => Entity::getProperties($entity, [Column::class]),
        ]);

        return $entity;
    }

    /**
     * The editor that snapshotWithEditor() recorded for a version, for display
     * in the version list. Returns null for versions written without an
     * authenticated editor (e.g. pw:flat:sync) or before editor capture existed.
     */
    public function editorOf(string $type, int|string $id, string $version): ?string
    {
        return $this->metadataOf($type, $id, $version)['editedBy']['username'] ?? null;
    }

    /** @return SnapshotMetadata */
    public function metadataOf(string $type, int|string $id, string $version): array
    {
        /** @var SnapshotMetadata $data */
        $data = json_decode($this->readSnapshot($type, $id, $version), true, flags: \JSON_THROW_ON_ERROR);

        return array_intersect_key($data, [
            'h1' => true,
            'title' => true,
            'name' => true,
            'slug' => true,
            'editMessage' => true,
            'holdPublicationAt' => true,
            'editedBy' => true,
        ]);
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

        return array_last($versions);
    }

    /**
     * Pick the previous distinct revision, skipping snapshots that mirror the
     * current state. When a live baseline is preferred for a held page, use the
     * newest live snapshot, or the oldest snapshot if they were all held.
     *
     * Returns null when the entity has no stored version.
     */
    public function pickComparisonVersion(string $type, int|string $id, bool $preferLiveVersion = true): ?string
    {
        $versions = $this->getVersions($type, $id);
        if ([] === $versions) {
            return null;
        }

        sort($versions); // uniqid prefix is chronological, oldest first

        $current = $this->find($type, $id);

        if ($preferLiveVersion && $current instanceof Page && $current->isHoldPublication()) {
            foreach (array_reverse($versions) as $version) {
                if (! $this->isHeldSnapshot($type, $id, $version)) {
                    return $version;
                }
            }

            return $versions[0]; // every version was held → oldest
        }

        $currentHash = sha1($this->revisions->serialize($current));
        foreach (array_reverse($versions) as $version) {
            if (! str_ends_with($version, '_'.$currentHash)) {
                return $version;
            }
        }

        return $versions[0]; // every version matches current → oldest
    }

    private function isHeldSnapshot(string $type, int|string $id, string $version): bool
    {
        return null !== ($this->metadataOf($type, $id, $version)['holdPublicationAt'] ?? null);
    }

    private function readSnapshot(string $type, int|string $id, string $version): string
    {
        $snapshot = $this->fileSystem->readFile($this->getVersionFile($type, $id, $version));

        // Legacy JSON snapshots share the same filenames as gzip snapshots.
        if (! str_starts_with($snapshot, "\x1f\x8b")) {
            return $snapshot;
        }

        $json = @gzdecode($snapshot);

        return false !== $json ? $json : throw new UnexpectedValueException('Invalid gzip snapshot '.$version);
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
