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

        /** @var callable */
        $callable = ['RegexHelper', 'prepareForRegex'];

        /** @var string[] */
        $var = array_map($callable, $var);

        return '('.implode('|', $var).')';
    }
}
