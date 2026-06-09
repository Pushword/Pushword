<?php

namespace Pushword\Api\DependencyInjection;

use Pushword\Core\DependencyInjection\ExtensionTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordApiExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    protected string $configFolder = __DIR__.'/../config';

    /**
     * @param array<mixed> $mergedConfig
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $deleteStrategy = \is_string($mergedConfig['delete_strategy'] ?? null) ? $mergedConfig['delete_strategy'] : 'hard';
        $softState = \is_string($mergedConfig['soft_delete_workflow_state'] ?? null) ? $mergedConfig['soft_delete_workflow_state'] : 'archived';

        $container->setParameter('pw.api.delete_strategy', $deleteStrategy);
        $container->setParameter('pw.api.soft_delete_workflow_state', $softState);

        $this->loadService($container);
    }
}
