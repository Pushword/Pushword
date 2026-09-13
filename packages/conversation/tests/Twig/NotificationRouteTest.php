<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Entity\Review;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Group('integration')]
final class NotificationRouteTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{class-string<Message>, string}>
     */
    public static function messageProvider(): iterable
    {
        yield 'message' => [Message::class, 'admin_conversation_edit'];
        yield 'review without rating' => [Review::class, 'admin_review_edit'];
    }

    /** @param class-string<Message> $class */
    #[DataProvider('messageProvider')]
    public function testNotificationLinksToTheCorrectEditor(string $class, string $route): void
    {
        self::bootKernel();

        $message = new $class();
        $message->setContent('A new message');
        new ReflectionProperty(Message::class, 'id')->setValue($message, 42);

        $twig = self::getContainer()->get('twig');
        $html = $twig->render('@PushwordConversation/conversation/notification.html.twig', [
            'messages' => [$message],
        ]);

        $router = self::getContainer()->get('router');
        $url = $router->generate($route, ['entityId' => 42], UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertStringContainsString('href="'.$url.'"', $html);
    }
}
