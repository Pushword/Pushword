<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Service\NewMessageMailNotifier;

final class HasReplyAddressTest extends TestCase
{
    /** @return iterable<string, array{?string, bool}> */
    public static function emailProvider(): iterable
    {
        yield 'valid address' => ['visitor@example.org', true];
        yield 'null' => [null, false];
        yield 'empty' => ['', false];
        yield 'no domain' => ['visitor@', false];
        yield 'not an address' => ['visitor', false];
        yield 'surrounding spaces' => [' visitor@example.org ', false];
    }

    #[DataProvider('emailProvider')]
    public function testOnlyAValidAuthorEmailIsAReplyAddress(?string $email, bool $expected): void
    {
        $message = new Message();
        $message->authorEmail = $email;

        self::assertSame($expected, NewMessageMailNotifier::hasReplyAddress($message));
    }
}
