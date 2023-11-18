<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\DependencyInjection;

use Pushword\Conversation\DependencyInjection\Configuration;
use Pushword\Conversation\DependencyInjection\PushwordConversationExtension;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\PushwordConversationBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        $msgEntity = self::$kernel->getContainer()->getParameter('pw.conversation.entity_message');

        $this->assertSame(Message::class, $msgEntity);

        $this->assertSame(
            'P1D',
            self::$kernel->getContainer()->get(\Pushword\Core\Component\App\AppPool::class)->get()->get('conversation_notification_interval')
        );

        $bundle = new PushwordConversationBundle();
        /** @var PushwordConversationExtension $extension */
        $extension = $bundle->getContainerExtension();
        $this->assertSame('conversation', $extension->getAlias());

        $parameterBag = new ParameterBag([]);
        $containerBuilder = new ContainerBuilder($parameterBag);
        $extension->prepend($containerBuilder);
        $this->assertContains('PushwordConversation', $containerBuilder->getExtensionConfig('twig')[0]['paths']);

        // $this->assertSame('', $parameterBag->get(''));

        $configuration = new Configuration();
        $this->assertSame(TreeBuilder::class, \get_class($configuration->getConfigTreeBuilder()));
    }
}
