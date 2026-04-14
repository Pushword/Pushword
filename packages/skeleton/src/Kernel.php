<?php

/**
 * Used for test.
 */

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        // Persistent shared cache so workers reuse the compiled container.
        // The dir is shared across all test runs/workers — Symfony invalidates it
        // automatically by tracking source file resources in the cache metadata.
        if ('test' === $this->environment) {
            return sys_get_temp_dir().'/com.github.pushword.pushword/container-cache/'.$this->environment;
        }

        // Dev cache lives under /tmp so phpstan-symfony can find the compiled
        // container at a deterministic location (see phpstan.dist.neon).
        return sys_get_temp_dir().'/com.github.pushword.pushword/tests/var/'.$this->environment.'/cache';
    }

    public function getLogDir(): string
    {
        // Shared across workers — the path is baked into the compiled container,
        // so it cannot vary per worker. Tests that need per-worker file isolation
        // must allocate their own subdirectory.
        return sys_get_temp_dir().'/com.github.pushword.pushword/tests/var/'.$this->environment.'/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.php');
        $container->import('../config/{packages}/*.{yaml,yml}');
        $container->import('../config/{packages}/'.$this->environment.'/*.php');
        $container->import('../config/{packages}/'.$this->environment.'/*.{yaml,yml}');

        if (is_file(\dirname(__DIR__).'/config/services.php')) {
            $container->import('../config/services.php');
            $container->import('../config/{services}_'.$this->environment.'.php');
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('../config/{routes}/'.$this->environment.'/*.php');
        $routes->import('../config/{routes}/*.php');

        if (is_file(\dirname(__DIR__).'/config/routes.yaml')) {
            $routes->import('../config/routes.yaml');
        }

        if (is_file(\dirname(__DIR__).'/config/routes.php')) {
            $routes->import('../config/routes.php');
        }
    }
}
