<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pushword\Core\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testConf(): void
    {
        $config = $this->process([]);

        self::assertSame($config['locale'], '%kernel.default_locale%');
    }

    protected function process(array $configs): array // @phpstan-ignore-line
    {
        $processor = new Processor();

        return $processor->processConfiguration(new Configuration(), $configs);
    }
}
