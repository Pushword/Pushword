<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait WeightTrait
{
    // name 'priority' permits backward compatibility
    #[ORM\Column(name: 'priority', type: Types::INTEGER, options: ['default' => 0])]
    private int $weight = 0;

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(string|int|null $value): bool
    {
        if (null === $value || '' === $value) {
            $this->weight = 0;

            return true;
        }

        if (\is_int($value)) {
            $this->weight = $value;

            return true;
        }

        $trimmed = trim($value);
        if ('' === $trimmed) {
            $this->weight = 0;

            return true;
        }

        if (! is_numeric($trimmed)) {
            return false;
        }

        $this->weight = (int) $trimmed;

        return true;
    }
}
