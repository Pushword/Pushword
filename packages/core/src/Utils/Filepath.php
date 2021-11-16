<?php

namespace Pushword\Core\Utils;

class Filepath
{
    public static function removeExtension(string $filepath): string
    {
        $pos = strrpos($filepath, '.');

        return false !== $pos ? \Safe\substr($filepath, 0, $pos) : $filepath;
    }

    public static function filename(string $filepath): string
    {
        $pos = strrpos($filepath, '/');

        return false !== $pos ? \Safe\substr($filepath, $pos + 1) : $filepath;
    }
}
