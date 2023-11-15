<?php

namespace Pushword\Core\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Pushword\Core\DependencyInjection\Configuration;
use Pushword\Core\DependencyInjection\PushwordConfigFactory;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Yaml\Yaml;

class PushwordConfigFactoryTest extends TestCase
{
    public function testWithoutConfiguration()
    {
        $container = new ContainerBuilder(new ParameterBag([]));
        $config = (new Processor())->processConfiguration(new Configuration(), []);
        $factory = new PushwordConfigFactory($container, $config, new Configuration());
        $factory->loadConfigToParams();
        $factory->loadApps();
        $this->assertSame('App\Entity\Page', $container->getParameter('pw.entity_page'));
        $this->assertTrue(! empty($container->getParameter('pw.apps')));
    }

    public function testIt()
    {
        $container = new ContainerBuilder(new ParameterBag([]));

        $factory = new PushwordConfigFactory($container, $this->getConfigArray(), new Configuration());
        $factory->loadConfigToParams();

        $this->assertSame('App\Entity\Page', $container->getParameter('pw.entity_page'));
        $this->assertFalse($container->hasParameter('pw.custom_property'));
        $this->assertFalse($container->hasParameter('pw.apps'));

        $factory->loadApps();

        $this->assertTrue($container->hasParameter('pw.apps'));
        $this->assertSame('Pushword', $container->getParameter('pw.apps')['localhost.dev']['name']);

        $apps = $container->getParameter('pw.apps');

        $factory->processAppsConfiguration(); // no need for it because loadApps ever did it

        $this->assertSame($apps, $container->getParameter('pw.apps'));

        $factory = new PushwordConfigFactory($container, $this->getPwExtensionConfig(), new Configuration(), 'anPushwordExtension');
        $factory->processAppsConfiguration(); // no need for it because loadApps ever did it

        $this->assertFalse($container->hasParameter('pw.anPushwordExtension.randomConfigParamsNeededForApp'));
        $this->assertFalse($container->hasParameter('pw.randomConfigParamsNeededForApp'));
        $this->assertSame('ok', $container->getParameter('pw.apps')['localhost.dev']['randomConfigParamsNeededForApp']);
        $this->assertSame('blabla', $container->getParameter('pw.apps')['localhost.dev']['custom_properties']['firstCP']);
        $this->assertSame('blablabla', $container->getParameter('pw.apps')['localhost.dev']['custom_properties']['otherCustomProperty']);
    }

    public function testBadFormattedConfigException()
    {
        $container = new ContainerBuilder(new ParameterBag([]));
        $factory = new PushwordConfigFactory($container, $this->getBadConfigArray(), new Configuration());
        $factory->loadConfigToParams();

        $this->expectException(InvalidConfigurationException::class);
        $factory->loadApps();
        // dd ($container->getParameter('pw.apps'));
    }

    private function getConfigArray(): array
    {
        $config = Yaml::parse(file_get_contents(__DIR__.'/../../../skeleton/config/packages/pushword.yaml'));
        $config['pushword']['apps'][0]['custom_properties'] = array_merge($config['pushword']['apps'][0]['custom_properties'] ?? [], ['firstCP' => 'blabla']);
        $config = (new Processor())->processConfiguration(new Configuration(), [$config['pushword']]);

        return $config;
    }

    private function getBadConfigArray(): array
    {
        $array = $this->getConfigArray();
        $array['apps'][0]['locale'] = '';

        return $array;
    }

    private function getPwExtensionConfig(): array
    {
        return [
            'app_fallback_properties' => ['randomConfigParamsNeededForApp', 'custom_properties'],
            'randomConfigParamsNeededGlobally(ParameterBag)' => 'ok',
            'randomConfigParamsNeededForApp' => 'ok',
            'custom_properties' => [
                'otherCustomProperty' => 'blablabla',
            ],
        ];
    }
}
