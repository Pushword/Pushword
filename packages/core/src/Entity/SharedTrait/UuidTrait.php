<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Stable cross-database identity for rows that merge between independently
 * written databases (flat CSV round-trips): auto-increment ids diverge once
 * two machines insert separately, uuids never collide. Nullable because rows
 * created before the column existed are backfilled on their next flat export.
 */
trait UuidTrait
{
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, nullable: true)]
    public ?string $uuid = null;

    public function getOrGenerateUuid(): string
    {
        return $this->uuid ??= Uuid::v4()->toRfc4122();
    }
}
