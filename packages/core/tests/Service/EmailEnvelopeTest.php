<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\Email\EmailEnvelope;

class EmailEnvelopeTest extends TestCase
{
    /**
     * @param string[] $to
     */
    #[DataProvider('provideIsValid')]
    public function testIsValid(bool $expected, string $from, array $to): void
    {
        $envelope = new EmailEnvelope($from, $to);

        self::assertSame($expected, $envelope->isValid());
    }

    /**
     * @return iterable<string, array{bool, string, string[]}>
     */
    public static function provideIsValid(): iterable
    {
        yield 'valid single recipient' => [true, 'from@example.com', ['to@example.com']];
        yield 'valid multiple recipients' => [true, 'from@example.com', ['a@example.com', 'b@example.com']];
        yield 'empty from' => [false, '', ['to@example.com']];
        yield 'invalid from' => [false, 'not-an-email', ['to@example.com']];
        yield 'empty recipients' => [false, 'from@example.com', []];
        yield 'invalid recipient' => [false, 'from@example.com', ['invalid']];
    }

    public function testGetFirstRecipient(): void
    {
        $envelope = new EmailEnvelope('from@example.com', ['first@example.com', 'second@example.com']);

        self::assertSame('first@example.com', $envelope->getFirstRecipient());
    }

    public function testGetFirstRecipientWithEmptyArray(): void
    {
        $envelope = new EmailEnvelope('from@example.com', []);

        self::assertSame('', $envelope->getFirstRecipient());
    }

    public function testWithReplyTo(): void
    {
        $envelope = new EmailEnvelope('from@example.com', ['to@example.com']);
        $withReply = $envelope->withReplyTo('reply@example.com');

        self::assertSame('reply@example.com', $withReply->replyTo);
        self::assertSame('from@example.com', $withReply->from);
        self::assertSame(['to@example.com'], $withReply->to);
        self::assertNull($envelope->replyTo);
    }

    public function testConstructorWithReplyTo(): void
    {
        $envelope = new EmailEnvelope('from@example.com', ['to@example.com'], 'reply@example.com');

        self::assertSame('reply@example.com', $envelope->replyTo);
    }
}
