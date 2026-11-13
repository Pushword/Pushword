<?php

declare(strict_types=1);

namespace Pushword\TwigStanTool;

use App\Kernel;
use Override;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class AnalysisKernel extends Kernel
{
    #[Override]
    public function getCacheDir(): string
    {
        return __DIR__.'/var/container/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return __DIR__.'/var/log';
    }

    #[Override]
    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container->withPath($this->getProjectDir().'/src/Kernel.php'));
    }

    #[Override]
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        parent::configureRoutes($routes->withPath($this->getProjectDir().'/src/Kernel.php'));
    }
}
