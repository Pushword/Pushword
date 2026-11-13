<?php

declare(strict_types=1);

namespace Pushword\TwigStan;

use LogicException;
use Override;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/** Keeps the analysis container separate while preserving the project's kernel configuration. */
trait AnalysisKernelTrait
{
    #[Override]
    public function getCacheDir(): string
    {
        return Runtime::cacheDirectory().'/container/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return Runtime::cacheDirectory().'/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        new ReflectionClass(parent::class)->getMethod('configureContainer')->invoke($this, $container->withPath($this->projectKernelFile()));
        $container->extension('framework', ['test' => true]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        new ReflectionClass(parent::class)->getMethod('configureRoutes')->invoke($this, $routes->withPath($this->projectKernelFile()));
    }

    private function projectKernelFile(): string
    {
        return new ReflectionClass(parent::class)->getFileName() ?: throw new LogicException('The analysis kernel must extend a project kernel declared in a file.');
    }
}
