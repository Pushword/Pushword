<?php

namespace Pushword\PageScanner\Scanner;

/**
 * Permit to find error in image or link.
 */
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

        $var = array_map('static::prepareForRegex', $var); // @phpstan-ignore-line

        return '('.implode('|', $var).')';
    }
}
