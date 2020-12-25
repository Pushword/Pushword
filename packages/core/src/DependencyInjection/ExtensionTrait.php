<?php

namespace Pushword\Core\DependencyInjection;

use Exception;
use LogicException;

use function Safe\file_get_contents;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Parser;

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
        if (! file_exists($this->getConfigFolder().'/packages')) {
            return;
        }

        // Load configurations for other package
        $parser = new Parser();
        $finder = Finder::create()->files()->name('*.yaml')->in($this->getConfigFolder().'/packages');
        foreach ($finder as $singleFinder) {
            $configs = $parser->parse(file_get_contents($singleFinder->getRealPath()));
            if (! \is_array($configs)) {
                throw new Exception($singleFinder->getRealPath().' is malformed');
            }

            $this->prependExtensionConfigs($configs, $container);
        }

        $finder = Finder::create()->files()->name('*.php')->in($this->getConfigFolder().'/packages');
        foreach ($finder as $singleFinder) {
            /** @psalm-suppress UnresolvableInclude */
            $configs = @include $singleFinder->getRealPath();
            if (! \is_array($configs)) {
                throw new Exception();
            }

            $this->prependExtensionConfigs($configs, $container);
        }
    }

    /**
     * @param array<mixed> $configs
     */
    protected function prependExtensionConfigs(array $configs, ContainerBuilder $container): void
    {
        foreach ($configs as $name => $config) {
            if ('services' == $name) {
                continue;
            }

            if (! \is_array($config)) {
                throw new Exception('Malformed config named `'.((string) $name).'`');
            }

            /** @var array<string, mixed> $config */
            $container->prependExtensionConfig((string) $name, $config);
        }
    }

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
