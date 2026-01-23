<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait HostTrait
{
    #[ORM\Column(type: Types::STRING, length: 253)]
    public string $host = '' {
        set(?string $value) => $this->host = (string) $value;
    }
}
