<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\DependencyInjection;

use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        $flatContentDir = self::getContainer()->get(SiteRegistry::class)->get()->get('flat_content_dir');
        self::assertIsString($flatContentDir);
        self::assertStringEndsWith('/content/_host_', $flatContentDir);

        self::assertSame(self::getContainer()->getParameter('kernel.project_dir').'/../docs/content', self::getContainer()->get(SiteRegistry::class)->get('pushword.piedweb.com')->get('flat_content_dir'));
    }
}
