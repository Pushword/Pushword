<?php

namespace Pushword\StaticGenerator;

use function Safe\preg_match;

class Helper
{
    public static function isAbsolutePath(string $path): bool
    {
        return '' !== $path && (\in_array($path[0], [\DIRECTORY_SEPARATOR, '%'], true) || 1 === preg_match('#\A[A-Z]:(?![^/\\\\])#i', $path));
    }
}
