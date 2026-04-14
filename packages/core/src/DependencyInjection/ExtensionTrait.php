<?php

namespace Pushword\Core\DependencyInjection;

use LogicException;
use ReflectionProperty;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Finder\Finder;

trait ExtensionTrait
{
    private function getConfigFolder(): string
    {
        // if (! $this->configFolder) {
        //    throw new Exception('You must define `configFolder` in class using '.self::class);
        // }

        return $this->configFolder;
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependPackagesConfig($container);
    }

    protected function prependPackagesConfig(ContainerBuilder $container): void
    {
        $configFolder = $this->getConfigFolder().'/packages';
        if (! file_exists($configFolder)) {
            return;
        }

        // Snapshot extension configs before loading bundle defaults
        /** @var array<string, int> $configCountsBefore */
        $configCountsBefore = [];
        $refl = new ReflectionProperty(ContainerBuilder::class, 'extensionConfigs');
        /** @var array<string, list<array<string, mixed>>> $extensionConfigs */
        $extensionConfigs = $refl->getValue($container);
        foreach ($extensionConfigs as $ext => $configs) {
            $configCountsBefore[$ext] = \count($configs);
        }

        // Load YAML files first (for backward compatibility)
        $yamlLoader = new YamlFileLoader($container, new FileLocator($configFolder));
        $yamlFinder = Finder::create()->files()->name('*.yaml')->in($configFolder);
        foreach ($yamlFinder as $file) {
            $yamlLoader->load($file->getFilename());
        }

        // Load PHP files (new format - takes precedence)
        $phpLoader = new PhpFileLoader($container, new FileLocator($configFolder));
        $phpFinder = Finder::create()->files()->name('*.php')->in($configFolder);
        foreach ($phpFinder as $file) {
            $phpLoader->load($file->getFilename());
        }

        // File loaders append configs (via ContainerConfigurator::extension()),
        // but prepend() should produce low-priority defaults that app configs can override.
        // Move newly appended configs to the front so app-level configs always win.
        /** @var array<string, list<array<string, mixed>>> $allConfigs */
        $allConfigs = $refl->getValue($container);
        foreach ($allConfigs as $ext => $configs) {
            $countBefore = $configCountsBefore[$ext] ?? 0;
            if (\count($configs) > $countBefore) {
                $newConfigs = \array_slice($configs, $countBefore);
                $existingConfigs = \array_slice($configs, 0, $countBefore);
                $allConfigs[$ext] = [...$newConfigs, ...$existingConfigs];
            }
        }

        $refl->setValue($container, $allConfigs);
    }

    // Used in PushwordAdminExtension

    /**
     * @return ConfigurationInterface|null
     */
    abstract public function getConfiguration(array $config, ContainerBuilder $container);

    /**
     * @param mixed[] $mergedConfig
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($mergedConfig, $container) ?? throw new LogicException(); // @phpstan-ignore-line

        new PushwordConfigFactory($container, $mergedConfig, $configuration, $this->getAlias())
            ->loadConfigToParams()
            ->processAppsConfiguration();

        $this->loadService($container);
    }

    protected function loadService(ContainerBuilder $container): void
    {
        if (file_exists($this->getConfigFolder().'/services.yaml')) {
            $loader = new YamlFileLoader($container, new FileLocator($this->getConfigFolder()));
            $loader->load($this->getConfigFolder().'/services.yaml');

            return;
        }

        if (file_exists($this->getConfigFolder().'/services.php')) {
            $loader = new PhpFileLoader($container, new FileLocator($this->getConfigFolder()));
            $loader->load($this->getConfigFolder().'/services.php');
        }
    }
}
