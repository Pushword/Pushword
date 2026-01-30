<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\DependencyInjection;

use Pushword\Conversation\DependencyInjection\PushwordConversationExtension;
use Pushword\Conversation\PushwordConversationBundle;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

class ConfigurationTest extends KernelTestCase
{
    public function testConf(): void
    {
        self::bootKernel();

        self::assertSame('P1D', self::getContainer()->get(SiteRegistry::class)->get()->get('conversation_notification_interval'));

        $bundle = new PushwordConversationBundle();
        /** @var PushwordConversationExtension $extension */
        $extension = $bundle->getContainerExtension();
        self::assertSame('conversation', $extension->getAlias());

        // Verify Twig paths are configured
        $twig = self::getContainer()->get(Twig::class);
        $loader = $twig->getLoader();

        self::assertInstanceOf(FilesystemLoader::class, $loader);

        /** @var FilesystemLoader $loader */
        $paths = $loader->getPaths('PushwordConversation');
        self::assertNotEmpty($paths);
    }
}
