<?php

namespace Pushword\Installer;

class PostAutoloadDump extends PostInstall
{
    public static function run(): void
    {
        $packages = self::scanDir('vendor/pushword');

        foreach ($packages as $package) {
            self::runUpdate($package);
        }
    }

    private static function runUpdate(string $package): void
    {
        if (! file_exists('vendor/pushword/'.$package.'/src/Installer')) {
            return;
        }

        $scriptsToRun = self::scanDir('vendor/pushword/'.$package.'/src/Installer');
        foreach ($scriptsToRun as $i => $script) {
            if (! file_exists($isInstalledFile = 'var/installer/'.md5($package.$script))
                && ! str_ends_with($script, '/install.php')
            ) {
                echo '~ Executing '.$package.' update ('.$i++.').'.\chr(10);
                include $script;

                self::dumpFile($isInstalledFile, 'done');
            }
        }
    }
}
