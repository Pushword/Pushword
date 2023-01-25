<?php

namespace Pushword\Core\Entity\SharedTrait;

interface TimestampableInterface
{
    public function setCreatedAt(\DateTime|\DateTimeImmutable $createdAt): self;

    public function getCreatedAt(bool $safe = true): ?\DateTimeInterface;

    public function safegetCreatedAt(): \DateTimeInterface;

    public function setUpdatedAt(\DateTime|\DateTimeImmutable $updatedAt): self;

    public function getUpdatedAt(bool $safe = true): ?\DateTimeInterface;

    public function safegetUpdatedAt(): \DateTimeInterface;
}
