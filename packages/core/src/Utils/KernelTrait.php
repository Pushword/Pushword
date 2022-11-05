<?php

namespace Pushword\Core\Utils;

use Symfony\Component\HttpKernel\KernelInterface;

trait KernelTrait
{
    public static ?KernelInterface $appKernel = null;

    protected KernelInterface $kernel;

    public static function loadKernel(KernelInterface $kernel): void
    {
        if (null === static::$appKernel) {
            $kernelClass = $kernel::class;
            $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'];
            // file_put_contents('debug', $env, FILE_APPEND);
            static::$appKernel = new $kernelClass('test' == $env ? 'test' : 'prod', true);
            // static::$appKernel = clone $kernel;
            // NOTE: If we clone, it's take too much time in dev mod

            // $warmupDir = static::$appKernel->getBuildDir();
            // dd( $warmupDir);
            // static::$appKernel->reboot($warmupDir);
            static::$appKernel->boot();
        }
    }

    public static function getKernel(): KernelInterface
    {
        if (null === self::$appKernel) {
            throw new \LogicException('You must load kernel before to get It');
        }

        return self::$appKernel;
    }
}
