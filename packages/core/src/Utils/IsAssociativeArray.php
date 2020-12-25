<?php

namespace Pushword\Core\Utils;

class IsAssociativeArray
{
    public static function test(array $array)
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, \count($array) - 1);
    }
}
