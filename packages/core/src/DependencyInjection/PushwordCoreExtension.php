<?php

namespace Pushword\Core\DependencyInjection;

use LogicException;
use Override;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\DQL\JsonExtractFunction;
use Symfony\Component\Config\Definition\Processor;
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

        new PushwordConfigFactory($container, $mergedConfig, $configuration)
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

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependPackagesConfig($container);

        $this->registerResolveTargetEntities($container);
        $this->registerDqlFunctions($container);
        $this->registerDefaultWorkflow($container);
    }

    /**
     * Ship the default editorial workflow. Skipped when the application already
     * defines a "page_editorial" workflow, so end users can fully override the
     * places/transitions/guards from their own framework config.
     */
    private function registerDefaultWorkflow(ContainerBuilder $container): void
    {
        foreach ($container->getExtensionConfig('framework') as $frameworkConfig) {
            if (isset($frameworkConfig['workflows']) && \is_array($frameworkConfig['workflows'])
                && isset($frameworkConfig['workflows']['page_editorial'])) {
                return;
            }
        }

        $container->prependExtensionConfig('framework', [
            'workflows' => [
                'page_editorial' => [
                    'type' => 'state_machine',
                    'marking_store' => ['type' => 'method', 'property' => 'workflowState'],
                    'supports' => [Page::class],
                    'initial_marking' => 'draft',
                    'places' => ['draft', 'in_review', 'approved'],
                    'transitions' => [
                        'submit' => ['from' => 'draft', 'to' => 'in_review'],
                        'approve' => ['from' => 'in_review', 'to' => 'approved', 'guard' => "is_granted('ROLE_EDITOR')"],
                        'request_changes' => ['from' => ['in_review', 'approved'], 'to' => 'draft'],
                    ],
                ],
            ],
        ]);
    }

    private function registerDqlFunctions(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'dql' => [
                    'string_functions' => [
                        'JSON_EXTRACT' => JsonExtractFunction::class,
                    ],
                ],
            ],
        ]);
    }

    private function registerResolveTargetEntities(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig('pushword');
        $config = new Processor()->processConfiguration(new Configuration(), $configs);

        $resolveTargets = [];
        if (User::class !== $config['entity_user']) {
            $resolveTargets[User::class] = $config['entity_user'];
        }

        if ([] !== $resolveTargets) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'resolve_target_entities' => $resolveTargets,
                ],
            ]);
        }
    }

    #[Override]
    public function getAlias(): string
    {
        return 'pushword';
    }
}
