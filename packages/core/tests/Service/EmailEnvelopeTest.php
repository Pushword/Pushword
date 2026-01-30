<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\Email\EmailEnvelope;

class EmailEnvelopeTest extends TestCase
{
    public function testIsValidWithValidData(): void
    {
        $envelope = new EmailEnvelope('from@example.com', ['to@example.com']);

        self::assertTrue($envelope->isValid());
    }

    public function testIsValidWithEmptyFrom(): void
    {
        $envelope = new EmailEnvelope('', ['to@example.com']);

        self::assertFalse($envelope->isValid());
    }

    public function testIsValidWithInvalidFrom(): void
    {
        $envelope = new EmailEnvelope('not-an-email', ['to@example.com']);

        self::assertFalse($envelope->isValid());
    }

    public function testIsValidWithEmptyRecipients(): void
    {
        $envelope = new EmailEnvelope('from@example.com', []);

        self::assertFalse($envelope->isValid());
    }

    public function testIsValidWithInvalidRecipient(): void
    {
        $envelope = new EmailEnvelope('from@example.com', ['invalid']);

        self::assertFalse($envelope->isValid());
    }

    public function testIsValidWithMultipleRecipients(): void
    {
        $envelope = new EmailEnvelope('from@example.com', ['a@example.com', 'b@example.com']);

        self::assertTrue($envelope->isValid());
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
