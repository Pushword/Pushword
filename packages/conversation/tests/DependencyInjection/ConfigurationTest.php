<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\DependencyInjection;

use Pushword\Conversation\DependencyInjection\PushwordConversationExtension;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\PushwordConversationBundle;
use Pushword\Core\Component\App\AppPool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        $msgEntity = self::getContainer()->getParameter('pw.conversation.entity_message');

        self::assertSame(Message::class, $msgEntity);

        self::assertSame('P1D', self::getContainer()->get(AppPool::class)->get()->get('conversation_notification_interval'));

        $bundle = new PushwordConversationBundle();
        /** @var PushwordConversationExtension $extension */
        $extension = $bundle->getContainerExtension();
        self::assertSame('conversation', $extension->getAlias());

        $parameterBag = new ParameterBag([]);
        $containerBuilder = new ContainerBuilder($parameterBag);
        $extension->prepend($containerBuilder);

        $twigPaths = $containerBuilder->getExtensionConfig('twig')[0]['paths'];
        self::assertIsIterable($twigPaths);
        self::assertContains('PushwordConversation', $twigPaths);
    }
}
