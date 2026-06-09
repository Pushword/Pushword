<?php

namespace Pushword\Version\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Version\Repository\VersionLogRepository;

/**
 * Denormalized activity-stream row, one per versionable action (create / update /
 * restore). Written via a direct DBAL insert at the moment the action happens
 * (safe inside a Doctrine flush), read back as a plain queryable log so the admin
 * journal needs zero file reads and zero entity hydration. The snapshot files in
 * the Versionner storage dir remain the source of truth for content and diffs;
 * this table is only the "who did what, when" index.
 */
#[ORM\Entity(repositoryClass: VersionLogRepository::class)]
#[ORM\Table(name: 'version_log')]
#[ORM\Index(name: 'idx_version_log_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_version_log_entity', columns: ['type', 'entity_id'])]
class VersionLog
{
    public const string ACTION_CREATED = 'created';

    public const string ACTION_UPDATED = 'updated';

    public const string ACTION_RESTORED = 'restored';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    /** Versionable type slug, e.g. `page` or `snippet`. */
    #[ORM\Column(type: Types::STRING, length: 20)]
    public string $type = 'page';

    #[ORM\Column(name: 'entity_id', type: Types::INTEGER)]
    public int $entityId = 0;

    /** Snapshot filename this action produced (null for actions without a snapshot). */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    public ?string $version = null;

    /** One of the ACTION_* constants. */
    #[ORM\Column(type: Types::STRING, length: 20)]
    public string $action = self::ACTION_UPDATED;

    /** Acting user identifier; null for non-authenticated writes (flat sync, CLI). */
    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    public ?string $editor = null;

    /** Denormalized human label of the entity (its H1/title/name) at action time. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $title = null;

    /** Denormalized slug of the entity at action time (second line of the journal). */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $slug = null;

    /** Denormalized host (locale/site) the entity belonged to. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $host = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;
}
