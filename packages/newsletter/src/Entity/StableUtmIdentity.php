<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ReflectionClass;
use Symfony\Component\Uid\Uuid;

trait StableUtmIdentity
{
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $utmId = null;

    public function getUtmId(): string
    {
        // Existing rows get a deterministic identity without a data migration.
        return $this->utmId ??= strtolower(new ReflectionClass(self::class)->getShortName()).'-'.($this->id ?? Uuid::v7()->toRfc4122());
    }
}
