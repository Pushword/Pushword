<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Support;

use Pushword\Core\Service\Markdown\MarkdownParser;
use ReflectionClassConstant;
use RuntimeException;

/**
 * The parser version every markdown cache key carries, read from the constant
 * instead of copied beside each key a test spells out. MarkdownParser bumps it
 * whenever render output changes, and the Rust suite that also spells those keys
 * runs outside `composer test`, so a copy left behind there stays green locally
 * and only turns CI red.
 */
final class MarkdownCacheVersion
{
    public static function get(): string
    {
        $version = new ReflectionClassConstant(MarkdownParser::class, 'CACHE_VERSION')->getValue();
        if (! \is_int($version)) {
            throw new RuntimeException('MarkdownParser::CACHE_VERSION is not an int.');
        }

        return (string) $version;
    }
}
