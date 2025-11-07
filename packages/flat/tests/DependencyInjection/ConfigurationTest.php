<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\DependencyInjection;

use Pushword\Core\Component\App\AppPool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        // $msgEntity = self::getContainer()->getParameter('pw.conversation.entity_message');
        // $this->assertSame(Message::class, $msgEntity);
        self::assertSame(self::getContainer()->getParameter('kernel.project_dir').'/content/_host_', self::getContainer()->get(AppPool::class)->get()->get('flat_content_dir'));

        self::assertSame(self::getContainer()->getParameter('kernel.project_dir').'/../docs/content', self::getContainer()->get(AppPool::class)->get('pushword.piedweb.com')->get('flat_content_dir'));
    }
}
