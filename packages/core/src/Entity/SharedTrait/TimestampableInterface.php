<?php

namespace Pushword\Core\Entity\SharedTrait;

use DateTimeInterface;

interface TimestampableInterface
{
    /**
     * @param \DateTime|\DateTimeImmutable $createdAt */
    public function setCreatedAt(DateTimeInterface $createdAt): self;

    public function getCreatedAt(bool $safe = true): ?DateTimeInterface;

    public function safegetCreatedAt(): DateTimeInterface;

    /**
     * @param \DateTime|\DateTimeImmutable $updatedAt
     */
    public function setUpdatedAt(DateTimeInterface $updatedAt): self;

    public function getUpdatedAt(bool $safe = true): ?DateTimeInterface;

    public function safegetUpdatedAt(): DateTimeInterface;
}
