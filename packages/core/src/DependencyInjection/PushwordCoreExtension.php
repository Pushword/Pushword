<?php

namespace Pushword\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordCoreExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    private $configFolder = __DIR__.'/../Resources/config';

    protected function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $this->setPathParameters($container);

        (new PushwordConfigFactory($container, $mergedConfig, $this->getConfiguration($mergedConfig, $container)))
            ->loadConfigToParams()
            ->loadApps();

        $this->loadService($container);
    }

    private function setPathParameters(ContainerBuilder $container): void
    {
        if (false !== strpos(__DIR__, '/vendor/')) {
            $container->setParameter('pw.package_dir', '%kernel.project_dir%/../vendor/pushword');
            $container->setParameter('vendor_dir', '%kernel.root_dir%/../vendor');

            return;
        }

        $container->setParameter('vendor_dir', '%kernel.project_dir%/../../vendor');
        $container->setParameter('pw.package_dir', '%kernel.project_dir%/..');
    }

    public function getAlias()
    {
        return 'pushword';
    }
}
