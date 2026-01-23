<?php

namespace Pushword\Core\Utils;

class Filepath
{
    public static function removeExtension(string $filepath): string
    {
        $pos = strrpos($filepath, '.');

        // Include extensions up to 5 chars (e.g., .avif, .webp, .jpeg)
        return false !== $pos && (\strlen($filepath) - $pos) <= 5 ? substr($filepath, 0, $pos) : $filepath;
    }

    public static function filename(string $filepath): string
    {
        $pos = strrpos($filepath, '/');

        return false !== $pos ? substr($filepath, $pos + 1) : $filepath;
    }
}
