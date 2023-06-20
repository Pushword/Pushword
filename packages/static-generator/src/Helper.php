<?php

namespace Pushword\StaticGenerator;

class Helper
{
    public static function isAbsolutePath(string $path): bool
    {
        return '' !== $path && (\in_array($path[0], [\DIRECTORY_SEPARATOR, '%'], true) || 1 === \Safe\preg_match('#\A[A-Z]:(?![^/\\\\])#i', $path));
    }
}
