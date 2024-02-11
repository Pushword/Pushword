<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait HostTrait
{
    #[ORM\Column(type: Types::STRING, length: 253)]
    protected string $host = '';

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(?string $host): self
    {
        $this->host = (string) $host;

        return $this;
    }
}
