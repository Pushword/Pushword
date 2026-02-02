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
        return $this->getTestBaseDir().'/cache';
    }

    public function getLogDir(): string
    {
        return $this->getTestBaseDir().'/log';
    }

    private function getTestBaseDir(): string
    {
        $runId = \is_string($_ENV['TEST_RUN_ID'] ?? null) ? $_ENV['TEST_RUN_ID'] : (\is_string($_SERVER['TEST_RUN_ID'] ?? null) ? $_SERVER['TEST_RUN_ID'] : '');
        $segment = '' !== $runId ? '/'.$runId : '';

        return sys_get_temp_dir().'/com.github.pushword.pushword/tests'.$segment.'/var/'.$this->environment;
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
