<?php

declare(strict_types=1);

namespace Pushword\Installer;

use App\Kernel;
use Composer\Script\Event;
use Exception;
use Symfony\Component\Dotenv\Dotenv;

class PostAutoloadDump extends PostInstall
{
    private static ?Kernel $kernel = null;

    public static function runPostAutoload(Event $event): void
    {
        /** @var string $vendorDir */
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        require $vendorDir.'/autoload.php';

        $pushwordDir = $vendorDir.'/pushword';
        if (! is_dir($pushwordDir)) {
            return;
        }

        $packages = self::scanDir($pushwordDir);
        if (is_dir($pushwordDir.'/pushword/packages')) {
            $packages = array_merge($packages, self::scanDir($pushwordDir.'/pushword/packages'));
        }

        foreach ($packages as $package) {
            $monorepoPath = $pushwordDir.'/pushword/packages/'.$package;
            self::runUpdate(is_dir($monorepoPath) ? $monorepoPath : $pushwordDir.'/'.$package, $package);
        }
    }

    private static function runUpdate(string $packageDir, string $package): void
    {
        if (! file_exists($packageDir.'/src/Installer')) {
            return;
        }

        $scriptsToRun = self::scanDir($packageDir.'/src/Installer');
        foreach ($scriptsToRun as $i => $script) {
            if (! file_exists($isInstalledFile = 'var/installer/'.md5($package.$script)) && ! str_ends_with($script, '~')) {
                self::getKernel();

                echo '~ Executing '.$package.' update ('.$i++.').'.\chr(10);
                $className = '\\Pushword\\'.$package.'\\Installer\\'.basename($script, '.php');

                if (! class_exists($className) || ! method_exists($className, 'run')) {
                    throw new Exception();
                }

                new $className()->run();

                // TODO find a way to use autowiring
                // self::getKernel()->getContainer()->get($classname)->run();
                // include $script;

                self::dumpFile($isInstalledFile, 'done');
            }
        }
    }

    public static function getKernel(): Kernel
    {
        if (null !== self::$kernel) {
            return self::$kernel;
        }

        new Dotenv()->loadEnv(file_exists('.env') ? '.env' : 'packages/dev-app/.env');
        if (class_exists(Debug::class)) {
            Debug::enable();
        }

        self::$kernel = new Kernel('dev', true);

        self::$kernel->boot();

        return self::$kernel;
    }
}
