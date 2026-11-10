<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Twig;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Tests\Twig\Fixtures\NotificationOverrideKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

#[Group('integration')]
final class NotificationTemplateOverrideTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return NotificationOverrideKernel::class;
    }

    /** @return iterable<string, array{string, string}> */
    public static function templateProvider(): iterable
    {
        yield 'subject' => ['new_message.subject.txt.twig', 'Overridden subject'];
        yield 'html' => ['new_message.html.twig', '<p>Overridden HTML</p>'];
        yield 'text' => ['new_message.txt.twig', 'Overridden text'];
    }

    #[DataProvider('templateProvider')]
    public function testApplicationBundleOverridesTakePriority(string $template, string $expected): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);

        self::assertSame($expected."\n", $twig->render('@PushwordConversation/conversation/'.$template));
    }
}
