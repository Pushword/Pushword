<?php

namespace Pushword\Core\Entity\SharedTrait;

interface Weightable
{
    /**
     * @return bool True if value was valid and set, false otherwise
     */
    public function setWeight(string|int|null $value): bool;
}
