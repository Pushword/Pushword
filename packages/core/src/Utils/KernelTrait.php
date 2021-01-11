<?php

namespace Pushword\Core\Utils;

use Symfony\Component\HttpKernel\KernelInterface;

trait KernelTrait
{
    public static $appKernel;
    protected $kernel;

    public static function loadKernel(KernelInterface $kernel)
    {
        if (null === static::$appKernel) {
            $kernelClass = \get_class($kernel);
            $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'];
            //file_put_contents('debug', $env, FILE_APPEND);
            static::$appKernel = new $kernelClass('test' == $env ? 'test' : 'prod', true);
            //static::$appKernel = clone $kernel;
            // NOTE: If we clone, it's take too much time in dev mod
            static::$appKernel->boot();
        }
    }
}
