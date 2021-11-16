<?php

namespace Pushword\Installer;

use Composer\Script\Event;
use Exception;
use LogicException;
use Symfony\Component\Filesystem\Filesystem;

if (! class_exists(Filesystem::class)) {
    require_once 'vendor/symfony/filesystem/Filesystem.php';
}

class PostInstall
{
    public static function postUpdateCommand(): void //Event $event
    {
        if (($dir = scandir('vendor/pushword')) === false) {
            throw new LogicException();
        }
        $packages = array_filter($dir, function ($package) { return ! \in_array($package, ['.', '..'], true); });

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

    public static function mirror(string $source, string $dest): void
    {
        (new Filesystem())->mirror($source, $dest);
    }

    public static function remove(string $path): void
    {
        (new Filesystem())->remove($path);
    }

    public static function dumpFile(string $path, string $content): void
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
