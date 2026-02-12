<?php

namespace Pushword\Core\DependencyInjection;

use LogicException;
use Override;
use Pushword\Core\Entity\User;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Finder\Finder;
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
        // Load packages config files (existing ExtensionTrait logic)
        $configFolder = $this->configFolder.'/packages';
        if (file_exists($configFolder)) {
            $yamlLoader = new YamlFileLoader($container, new FileLocator($configFolder));
            $yamlFinder = Finder::create()->files()->name('*.yaml')->in($configFolder);
            foreach ($yamlFinder as $file) {
                $yamlLoader->load($file->getFilename());
            }

            $phpLoader = new PhpFileLoader($container, new FileLocator($configFolder));
            $phpFinder = Finder::create()->files()->name('*.php')->in($configFolder);
            foreach ($phpFinder as $file) {
                $phpLoader->load($file->getFilename());
            }
        }

        $this->registerResolveTargetEntities($container);
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
