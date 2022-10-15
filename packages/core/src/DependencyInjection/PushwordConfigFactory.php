<?php

namespace Pushword\Core\DependencyInjection;

use InvalidArgumentException;
use LogicException;
use Pushword\Core\Utils\IsAssociativeArray;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PushwordConfigFactory
{
    private string $prefix;

    /** @param array<mixed> $config */
    public function __construct(
        private ContainerBuilder $container,
        private array $config,
        private ConfigurationInterface $configuration,
        string $prefix = ''
    ) {
        $this->prefix = 'pw.'.('' !== $prefix ? $prefix.'.' : '');
    }

    public function loadConfigToParams(): self
    {
        $this->loadToParameters($this->config, $this->prefix);

        return $this;
    }

    /**
     * @return array<string>
     */
    private function getAppFallbackConfig(): array
    {
        if (! isset($this->config['app_fallback_properties'])) {
            return [];
        }

        if (\is_string($this->config['app_fallback_properties'])) {
            $this->config['app_fallback_properties'] = explode(',', $this->config['app_fallback_properties']);
        }

        return $this->config['app_fallback_properties'];  // @phpstan-ignore-line
    }

    /**
     * load Apps config and retrieve fallback directly, no need to call processAppsConfiguration.
     */
    public function loadApps(): self
    {
        if (! isset($this->config['apps'])) {
            $this->setParameter('pw.apps', $this->parseApps([]));

            return $this;
        }

        if ($this->container->hasParameter('pw.apps')) { // @phpstan-ignore-line
            throw new InvalidArgumentException('Invalid "apps" name: parameter is ever registered.');
        }

        $this->setParameter('pw.apps', $this->parseApps($this->config['apps'])); // @phpstan-ignore-line

        return $this;
    }

    public function processAppsConfiguration(): void
    {
        if ([] === $this->getAppFallbackConfig()) {
            return;
        }

        if (! $this->container->hasParameter('pw.apps')) { // @phpstan-ignore-line
            throw new LogicException('You must register Pushword/CoreBundle in first (`pw.apps` is not loaded in ParameterBag.');
        }

        /** @var array<string, array<mixed>> */
        $apps = $this->container->getParameter('pw.apps');

        foreach ($apps as $host => $app) {
            $apps[$host] = $this->processAppConfig($app);
        }

        $this->container->setParameter('pw.apps', $apps);
    }

    /**
     * @param array<array<mixed>> $apps
     *
     * @return array<mixed>
     */
    private function parseApps(array $apps): array
    {
        $result = [];
        foreach ($apps as $app) {
            $app = $this->processAppConfig($app);
            if (! isset($app['hosts']) || ! \is_array($app['hosts']) || ! isset($app['hosts'][0])) { // normally, it's impossible to reach this
                throw new InvalidArgumentException('Something is badly configured in your pushword configuration file.');
            }

            $result[$app['hosts'][0]] = $app;
        }

        return $result;
    }

    /**
     * @param array<mixed> $app
     *
     * @return array<mixed>
     */
    private function processAppConfig(array $app): array
    {
        $fallbackProperties = $this->getAppFallbackConfig();

        $node = $this->configuration->getConfigTreeBuilder()->buildTree();
        $node->finalize($app); // it will check value

        if (! isset($app['hosts']) || ! \is_array($app['hosts'])) {
            $app = (new Processor())->processConfiguration($this->configuration, $app);
            // throw new LogicException();
        }

        foreach ($fallbackProperties as $fallbackProperty) {
            if (! isset($app[$fallbackProperty])) {
                $app[$fallbackProperty] = \is_string($this->config[$fallbackProperty]) ? str_replace('%main_host%', $app['hosts'][0], $this->config[$fallbackProperty])
                    : $this->config[$fallbackProperty];
            } elseif ('custom_properties' == $fallbackProperty) {
                if (! \is_array($this->config['custom_properties']) || ! \is_array($app['custom_properties'])) {
                    throw new LogicException();
                }

                $app['custom_properties'] = array_merge($this->config['custom_properties'], $app['custom_properties']);
            }
        }

        return $app;
    }

    /**
     * @param array<mixed> $config
     */
    private function loadToParameters(array $config, string $prefix = ''): void
    {
        $fallbackProperties = $this->getAppFallbackConfig();

        foreach ($config as $key => $value) {
            if ('apps' === $key) {
                continue; // We don't process Apps this way
            }

            if (\in_array($key, $fallbackProperties, true)) {
                continue; // We don't load configuration we use in App
            }

            if (\is_array($value)
            && 'image_filter_sets' !== $key
                && IsAssociativeArray::test($value)
            ) {
                $this->loadToParameters($value, $prefix.$key.'.');

                continue;
            }

            $this->setParameter($prefix.$key, $value); // @phpstan-ignore-line
        }
    }

    /**
     * @param array<mixed>|bool|string|int|float|null $value The parameter value
     *
     * @noRector
     */
    private function setParameter(string $key, $value): void
    {
        if ($this->container->hasParameter($key)) {
            throw new InvalidArgumentException(\Safe\sprintf('Invalid "%s" name: parameter is ever registered.', $key));
        }

        $this->container->setParameter($key, $value);
    }
}
