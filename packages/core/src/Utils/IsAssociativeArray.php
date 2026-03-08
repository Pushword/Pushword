<?php

declare(strict_types=1);

namespace Pushword\Core\Utils;

class IsAssociativeArray
{
    /**
     * @param array<mixed> $array
     */
    public static function test(array $array): bool
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, \count($array) - 1);
    }
}
