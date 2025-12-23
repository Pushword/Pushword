<?php

namespace Pushword\Core\Entity\SharedTrait;

use DateTimeInterface;

interface TimestampableInterface
{
    public DateTimeInterface $createdAt { get; set; }

    public DateTimeInterface $updatedAt { get; set; }

    public function getCreatedAtNullable(): ?DateTimeInterface;

    public function getUpdatedAtNullable(): ?DateTimeInterface;
}
