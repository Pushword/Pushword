<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\DependencyInjection;

use Pushword\Core\Component\App\AppPool;
use Pushword\StaticGenerator\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        $apps = self::getContainer()->get(AppPool::class);

        self::assertSame($apps->get()->get('static_copy'), Configuration::DEFAULT_COPY);
    }
}
