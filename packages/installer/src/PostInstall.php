<?php

namespace Pushword\Installer;

use Exception;
use LogicException;
use Symfony\Component\Filesystem\Filesystem;

if (! class_exists(Filesystem::class)) {
    require_once __DIR__.'/vendor/symfony/filesystem/Filesystem.php';
}

class PostInstall
{
    public static function runPostUpdate(): void // Event $event
    {
        $packages = self::scanDir('vendor/pushword');

        foreach ($packages as $package) {
            if (! file_exists('var/installer/'.md5($package)) && file_exists($installer = 'vendor/pushword/'.$package.'/install.php')) {
                echo '~ Executing '.$package.' install action.'.\chr(10);
                include $installer;

                self::dumpFile('var/installer/'.md5($package), 'done');
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public static function scanDir(string $dirPath): array
    {
        if (($dir = scandir($dirPath)) === false) {
            throw new LogicException();
        }

        return array_filter($dir, static fn (string $path): bool => ! \in_array($path, ['.', '..'], true));
    }

    public static function copy(string $source, string $dest): void
    {
        new Filesystem()->copy($source, $dest, true);
    }

    public static function mirror(string $source, string $dest): void
    {
        new Filesystem()->mirror($source, $dest);
    }

    /**
     * @param string|string[] $path
     */
    public static function remove(array|string $path): void
    {
        new Filesystem()->remove($path);
    }

    public static function dumpFile(string $path, string $content): void
    {
        new Filesystem()->dumpFile($path, $content);
    }

    public static function replace(string $file, string $search, string $replace): void
    {
        $content = file_get_contents($file);
        if (false === $content) {
            throw new Exception('`'.$file.'` not found');
        }

        $count = 0;
        $content = str_replace($search, $replace, $content, $count);
        if (1 !== $count) {
            echo '⚠ Warning: Could not replace `'.$search.'` by `'.$replace.'` in '.$file.\chr(10);

            return;
        }

        file_put_contents($file, $content);
    }

    public const INSERT_AT_BEGINNING = 'atBeggining';

    public const INSERT_AT_END = 'atEnd';

    public static function insertIn(string $file, string $toAdd, string $where = self::INSERT_AT_BEGINNING): void
    {
        $content = (string) @file_get_contents($file);
        if (str_contains($content, $toAdd)) {
            return;
        }

        if (self::INSERT_AT_BEGINNING === $where) {
            $content = $toAdd.$content;
        } elseif (self::INSERT_AT_END === $where) {
            $content .= $toAdd;
        } else {
            throw new Exception();
        }

        self::dumpFile($file, $content);
    }

    public static function isRoot(): bool
    {
        return file_exists('vendor');
    }
}
