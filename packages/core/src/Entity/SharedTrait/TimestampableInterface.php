<?php

namespace Pushword\Core\Entity\SharedTrait;

interface TimestampableInterface
{
    /** @return $this
     * @param \DateTime|\DateTimeImmutable $createdAt */
    public function setCreatedAt(\DateTimeInterface $createdAt);

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedAt();

    /**
     * @return $this
     *
     * @param \DateTime|\DateTimeImmutable $updatedAt
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt);

    /**
     * @return \DateTimeInterface
     */
    public function getUpdatedAt();
}
