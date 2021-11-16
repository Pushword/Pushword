<?php

namespace Pushword\Core\Entity\SharedTrait;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

trait TimestampableTrait
{
    /**
     * @var \DateTimeInterface
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface
     * @ORM\Column(type="datetime")
     */
    protected $updatedAt;

    /**
     * Sets createdAt.
     *
     * @return $this
     */
    public function setCreatedAt(\DateTimeInterface $createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Returns createdAt.
     *
     * @return \DateTimeInterface
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Sets updatedAt.
     *
     * @return $this
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Returns updatedAt.
     *
     * @return \DateTimeInterface
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function __constructTimestampable(): void
    {
        $this->updatedAt = null !== $this->updatedAt ? $this->updatedAt : new \DateTime();
        $this->createdAt = null !== $this->createdAt ? $this->createdAt : new \DateTime();
    }
}
