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
            static::$appKernel = new $kernelClass('dev', true);
            //static::$appKernel = clone $kernel;
            // NOTE: If we clone, it's take too much time in dev mod
            static::$appKernel->boot();
        }
    }
}
