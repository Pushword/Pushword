<?php

namespace Pushword\Core\DependencyInjection;

use LogicException;
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
        $configFolder = $this->getConfigFolder().'/packages';
        if (! file_exists($configFolder)) {
            return;
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

        (new PushwordConfigFactory($container, $mergedConfig, $configuration, $this->getAlias()))
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
