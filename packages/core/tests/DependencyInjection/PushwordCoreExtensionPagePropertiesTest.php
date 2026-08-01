<?php

namespace Pushword\Core\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pushword\Core\DependencyInjection\PushwordCoreExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class PushwordCoreExtensionPagePropertiesTest extends TestCase
{
    public function testTypoInConstraintNameFailsTheContainerBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Choise');

        $this->load([
            'page_properties' => ['level' => ['constraints' => [['Choise' => null]]]],
            'apps' => [['hosts' => ['example.tld']]],
        ]);
    }

    public function testTypoInDescriptorKeyFailsTheContainerBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('requird');

        $this->load([
            'apps' => [[
                'hosts' => ['example.tld'],
                'page_properties' => ['author' => ['requird' => true]],
            ]],
        ]);
    }

    /** @param array<string, mixed> $config */
    private function load(array $config): void
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.project_dir' => sys_get_temp_dir(),
            'kernel.default_locale' => 'en',
        ]));

        new PushwordCoreExtension()->load([$config], $container);
    }
}
