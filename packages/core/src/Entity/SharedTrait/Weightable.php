<?php

declare(strict_types=1);

namespace Pushword\Core\Entity\SharedTrait;

interface Weightable
{
    public int $weight { get; }

    /**
     * @return bool True if value was valid and set, false otherwise
     */
    public function setWeight(string|int|null $value): bool;
}
