<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\DependencyInjection;

use Pushword\Conversation\Entity\Message;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        $msgEntity = self::$kernel->getContainer()->getParameter('pw.conversation.entity_message');

        $this->assertSame(Message::class, $msgEntity);

        $this->assertSame(
            'P12H',
            self::$kernel->getContainer()->get('pushword.apps')->get()->get('conversation_notification_interval')
        );
    }
}
