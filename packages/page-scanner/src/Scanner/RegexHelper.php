<?php

namespace Pushword\PageScanner\Scanner;

final class RegexHelper
{
    /**
     * @param string|string[] $var
     */
    public static function prepareForRegex(array|string $var): string
    {
        if (\is_string($var)) {
            return preg_quote($var, '/');
        }

        $var = array_map(self::prepareForRegex(...), $var);

        return '('.implode('|', $var).')';
    }
}
