<?php

namespace Pushword\Core\Utils;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Path;

final class PathGuard
{
    public static function joinUnder(string $basePath, string ...$parts): string
    {
        $basePath = Path::canonicalize($basePath);
        if ('' === $basePath) {
            throw new InvalidArgumentException('The base path must not be empty.');
        }

        $path = Path::join($basePath, ...$parts);
        if (! Path::isBasePath($basePath, $path) || $path === $basePath) {
            throw new InvalidArgumentException('The resolved path escapes its configured directory.');
        }

        return $path;
    }
}
