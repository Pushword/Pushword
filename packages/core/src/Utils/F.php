<?php

namespace Pushword\Core\Utils;

use Exception;

class F
{
    public static function file_get_contents(string $filename): string
    {
        return \Safe\file_get_contents($filename);
    }

    /**
     * @param string|array $replacement
     * @param string|array $subject
     */
    public static function preg_replace_str(string $pattern, $replacement, $subject, int $limit = -1, int &$count = 0): string // @phpstan-ignore-line
    {
        $return = \Safe\preg_replace($pattern, $replacement, $subject, $limit, $count);

        // if (\gettype($pattern) !== \gettype($return)) {
        if (! \is_string($return)) {
            throw new Exception('An error occured on preg_replace');
        }

        return $return;
    }
}
