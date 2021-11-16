<?php

namespace Pushword\Core\Entity\SharedTrait;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

trait TimestampableTrait
{
    /**
     * @ORM\Column(type="datetime")
     * @noRector
     */
    protected ?DateTimeInterface $createdAt = null; // @phpstan-ignore-line

    /**
     * @ORM\Column(type="datetime")
     * @noRector
     */
    protected ?DateTimeInterface $updatedAt = null; // @phpstan-ignore-line

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(bool $safe = true): ?DateTimeInterface
    {
        if ($safe) {
            return $this->safegetCreatedAt();
        }

        return $this->createdAt;
    }

    public function safegetCreatedAt(): DateTimeInterface
    {
        if (null === $this->createdAt) {
            return new \DateTime();
        }

        return $this->createdAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function safegetUpdatedAt(): DateTimeInterface
    {
        if (null === $this->updatedAt) {
            return new \DateTime();
        }

        return $this->updatedAt;
    }

    public function getUpdatedAt(bool $safe = true): ?DateTimeInterface
    {
        if ($safe) {
            return $this->safegetUpdatedAt();
        }

        return $this->updatedAt;
    }

    public function initTimestampableProperties(): void
    {
        $this->updatedAt = null !== $this->updatedAt ? $this->updatedAt : new \DateTime();
        $this->createdAt = null !== $this->createdAt ? $this->createdAt : new \DateTime();
    }
}
