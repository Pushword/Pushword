<?php

namespace Pushword\Installer;

use Composer\Script\Event;
use Exception;
use Symfony\Component\Filesystem\Filesystem;

if (! class_exists(Filesystem::class)) {
    require_once 'vendor/symfony/filesystem/Filesystem.php';
}

class PostInstall
{
    public static function postUpdateCommand(): void //Event $event
    {
        $packages = array_filter(scandir('vendor/pushword'), function ($package) { return ! \in_array($package, ['.', '..']); });

        foreach ($packages as $package) {
            if (file_exists('vendor/pushword/'.$package) && ! file_exists('var/installer/'.md5($package))) {
                $installer = 'vendor/pushword/'.$package.'/install.php';
                if (file_exists($installer)) {
                    echo '~ Executing '.$package.' post update command install action.'.\chr(10);
                    include $installer;
                }

                self::dumpFile('var/installer/'.md5($package), 'done');
            }
        }
    }

    public static function mirror(string $source, string $dest)
    {
        (new Filesystem())->mirror($source, $dest);
    }

    public static function remove($path)
    {
        (new Filesystem())->remove($path);
    }

    public static function dumpFile(string $path, string $content)
    {
        (new Filesystem())->dumpFile($path, $content);
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
            throw new Exception('Error on replacing `'.$search.'` by `'.$replace.'`');
        }
        file_put_contents($file, $content);
    }

    public static function addOnTop(string $file, string $toAdd): void
    {
        $content = (string) @file_get_contents($file);
        if (false !== strpos($content, $toAdd)) {
            return;
        }
        $content = $toAdd.$content;
        self::dumpFile($file, $content);
    }

    public static function isRoot(): bool
    {
        return file_exists('vendor');
    }
}
