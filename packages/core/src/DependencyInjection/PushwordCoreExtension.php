<?php

namespace Pushword\Core\DependencyInjection;

use LogicException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordCoreExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    private string $configFolder = __DIR__.'/../Resources/config';

    /**
     * @param array<mixed> $mergedConfig
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $this->setPathParameters($container);

        $configuration = $this->getConfiguration($mergedConfig, $container) ?? throw new LogicException(); // @phpstan-ignore-line

        (new PushwordConfigFactory($container, $mergedConfig, $configuration))
            ->loadConfigToParams()
            ->loadApps();

        $this->loadService($container);
    }

    private function setPathParameters(ContainerBuilder $containerBuilder): void
    {
        if (file_exists($containerBuilder->getParameter('kernel.project_dir').'/vendor/pushword')) {
            // false !== strpos(__DIR__, '/vendor/')) {
            $containerBuilder->setParameter('pw.package_dir', '%kernel.project_dir%/vendor/pushword');
            $containerBuilder->setParameter('vendor_dir', '%kernel.project_dir%/vendor');

            return;
        }

        $containerBuilder->setParameter('vendor_dir', '%kernel.project_dir%/../../vendor');
        $containerBuilder->setParameter('pw.package_dir', '%kernel.project_dir%/..');
    }

    public function getAlias(): string
    {
        return 'pushword';
    }
}
