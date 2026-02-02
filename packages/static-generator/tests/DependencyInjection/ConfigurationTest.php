<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        $apps = self::getContainer()->get(SiteRegistry::class);

        self::assertSame($apps->get()->get('static_copy'), Configuration::DEFAULT_COPY);
    }
}
