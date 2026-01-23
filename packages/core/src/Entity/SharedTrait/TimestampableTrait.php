<?php

namespace Pushword\Core\Entity\SharedTrait;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ReflectionProperty;

trait TimestampableTrait
{
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public ?DateTimeInterface $createdAt = null { // @phpstan-ignore-line
        get => $this->createdAt ?? new DateTime();
        set => $this->createdAt = $value;
    }

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public ?DateTimeInterface $updatedAt = null { // @phpstan-ignore-line
        get => $this->updatedAt ?? new DateTime();
        set => $this->updatedAt = $value;
    }

    /**
     * Check if createdAt backing store is null (bypasses property hook).
     * Uses ReflectionProperty to access raw backing store value.
     */
    public function getCreatedAtNullable(): ?DateTimeInterface
    {
        $reflection = new ReflectionProperty($this, 'createdAt');

        return $reflection->getRawValue($this); // @phpstan-ignore return.type
    }

    /**
     * Check if updatedAt backing store is null (bypasses property hook).
     * Uses ReflectionProperty to access raw backing store value.
     */
    public function getUpdatedAtNullable(): ?DateTimeInterface
    {
        $reflection = new ReflectionProperty($this, 'updatedAt');

        return $reflection->getRawValue($this); // @phpstan-ignore return.type
    }

    #[ORM\PrePersist]
    public function initTimestampableProperties(): void
    {
        // Use reflection-based nullable getters to check actual backing store,
        // since property hooks return default DateTime on get even when backing store is null
        $this->updatedAt = $this->getUpdatedAtNullable() ?? new DateTime();
        $this->createdAt = $this->getCreatedAtNullable() ?? new DateTime();
    }
}
