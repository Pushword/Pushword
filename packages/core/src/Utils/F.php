<?php

namespace Pushword\Core\Utils;

use function Safe\file_get_contents;
use function Safe\preg_replace;

class F
{
    public static function file_get_contents(string $filename): string
    {
        return file_get_contents($filename);
    }

    public static function preg_replace_str(string $pattern, array|string $replacement, array|string $subject, int $limit = -1, int &$count = 0): string // @phpstan-ignore-line
    {
        $return = preg_replace($pattern, $replacement, $subject, $limit, $count);

        // if (\gettype($pattern) !== \gettype($return)) {
        if (! \is_string($return)) {
            throw new \Exception('An error occured on preg_replace');
        }

        return $return;
    }
}
