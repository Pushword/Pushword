<?php

declare(strict_types=1);

namespace Pushword\Svg\Tests\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        $this->assertStringContainsString(
            'font-awesome',
            self::$kernel->getContainer()->get(\Pushword\Core\Component\App\AppPool::class)->get()->get('svg_dir')[1]
        );
    }
}
