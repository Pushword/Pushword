<?php

namespace Pushword\Core\Entity\SharedTrait;

interface TimestampableInterface
{
    /** @return $this */
    public function setCreatedAt(\DateTime $createdAt);

    /**
     * @return \DateTime
     */
    public function getCreatedAt();

    /**
     * @return $this
     */
    public function setUpdatedAt(\DateTime $updatedAt);

    /**
     * @return \DateTime
     */
    public function getUpdatedAt();
}
