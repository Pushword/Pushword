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

        self::assertSame(self::getContainer()->getParameter('kernel.project_dir').'/content/_host_', self::getContainer()->get(SiteRegistry::class)->get()->get('flat_content_dir'));

        self::assertSame(self::getContainer()->getParameter('kernel.project_dir').'/../docs/content', self::getContainer()->get(SiteRegistry::class)->get('pushword.piedweb.com')->get('flat_content_dir'));
    }
}
