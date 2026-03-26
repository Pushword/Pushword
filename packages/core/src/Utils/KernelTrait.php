<?php

declare(strict_types=1);

namespace Pushword\Core\Utils;

use Exception;
use LogicException;
use Symfony\Component\HttpKernel\KernelInterface;

trait KernelTrait
{
    public static ?KernelInterface $appKernel = null;

    protected static ?KernelInterface $debugKernel = null;

    protected KernelInterface $kernel;

    public static function loadKernel(KernelInterface $kernel): void
    {
        if (null === static::$appKernel) {
            $kernelClass = $kernel::class;
            $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? throw new Exception();
            static::$appKernel = new $kernelClass('test' === $env ? 'test' : 'prod', false);
            static::$appKernel->boot();
        }
    }

    public static function getKernel(): KernelInterface
    {
        if (null === self::$appKernel) {
            throw new LogicException('You must load kernel before to get It');
        }

        return self::$appKernel;
    }

    public static function getDebugKernel(): KernelInterface
    {
        if (null === static::$debugKernel) {
            $main = static::getKernel();
            $kernelClass = $main::class;
            static::$debugKernel = new $kernelClass($main->getEnvironment(), true);
            static::$debugKernel->boot();
        }

        return static::$debugKernel;
    }
}
