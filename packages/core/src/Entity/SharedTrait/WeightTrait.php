<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait WeightTrait
{
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    public int $weight = 0;
}
