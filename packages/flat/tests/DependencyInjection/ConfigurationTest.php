<?php

namespace Pushword\Flat\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        $flatContentDir = self::getContainer()->get(SiteRegistry::class)->get()->get('flat_content_dir');
        self::assertIsString($flatContentDir);
        self::assertStringEndsWith('/content/_host_', $flatContentDir);

        $piedwebContentDir = self::getContainer()->get(SiteRegistry::class)->get('pushword.piedweb.com')->get('flat_content_dir');
        self::assertIsString($piedwebContentDir);
        // In test env, piedweb uses the same test env var (with _host_ placeholder) as the default host
        self::assertStringEndsWith('/content/_host_', $piedwebContentDir);
    }
}
