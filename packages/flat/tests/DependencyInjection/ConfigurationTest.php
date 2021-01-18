<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\DependencyInjection;

use Pushword\Conversation\Entity\Message;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        //$msgEntity = self::$kernel->getContainer()->getParameter('pw.conversation.entity_message');
        //$this->assertSame(Message::class, $msgEntity);
        $this->assertSame(
            '',
            self::$kernel->getContainer()->get('pushword.apps')->get()->get('flat_content_dir')
        );

        $this->assertSame(
            self::$kernel->getContainer()->getParameter('kernel.project_dir').'/../docs/content',
            self::$kernel->getContainer()->get('pushword.apps')->get('pushword.piedweb.com')->get('flat_content_dir')
        );
    }
}
