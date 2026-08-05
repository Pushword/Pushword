<?php

namespace Pushword\Newsletter\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Service\BounceSignature;

final class BounceSignatureTest extends TestCase
{
    private const string SECRET = 'a-secret-nobody-writing-a-bounce-has';

    public function testAnIdIssuedForAnAddressProvesThatAddress(): void
    {
        $signature = new BounceSignature(self::SECRET);

        self::assertTrue($signature->proves(
            'reader@example.tld',
            [$signature->messageId('reader@example.tld', 'news@example.com')],
        ));
    }

    /** The whole point: one reader's mail must not take another reader off the list. */
    public function testAnIdIssuedForSomebodyElseProvesNothing(): void
    {
        $signature = new BounceSignature(self::SECRET);

        self::assertFalse($signature->proves(
            'victim@example.tld',
            [$signature->messageId('reader@example.tld', 'news@example.com')],
        ));
    }

    /** A report that returned no copy of the message names no id at all. */
    public function testAReportThatNamedNoMessageProvesNothing(): void
    {
        self::assertFalse(new BounceSignature(self::SECRET)->proves('victim@example.tld', []));
    }

    public function testAnIdNobodySignedProvesNothing(): void
    {
        $signature = new BounceSignature(self::SECRET);

        self::assertFalse($signature->proves('victim@example.tld', [
            'nl.0123456789abcdef.'.str_repeat('0', 32).'@example.com',
            '20260803194516.ABCDEF@relay.example.tld',
            'nl.victim@example.tld',
            '',
        ]));
    }

    public function testAnIdSignedWithAnotherSecretProvesNothing(): void
    {
        $elsewhere = new BounceSignature('another install, another secret');

        self::assertFalse(new BounceSignature(self::SECRET)->proves(
            'reader@example.tld',
            [$elsewhere->messageId('reader@example.tld', 'news@example.com')],
        ));
    }

    /** A remote server echoes a `Final-Recipient` in whatever case it likes. */
    public function testTheAddressIsReadWhateverCaseItComesBackIn(): void
    {
        $signature = new BounceSignature(self::SECRET);

        self::assertTrue($signature->proves(
            'Reader@Example.TLD',
            [$signature->messageId('reader@example.tld', 'news@example.com')],
        ));
    }

    /**
     * An id fixed per recipient would repeat across every campaign, and an inbox
     * that deduplicates on `Message-ID` drops the repeat.
     */
    public function testTwoMailsToTheSameReaderCarryDifferentIds(): void
    {
        $signature = new BounceSignature(self::SECRET);

        self::assertNotSame(
            $signature->messageId('reader@example.tld', 'news@example.com'),
            $signature->messageId('reader@example.tld', 'news@example.com'),
        );
    }

    public function testTheIdBelongsToTheSendingDomain(): void
    {
        $messageId = new BounceSignature(self::SECRET)->messageId('reader@example.tld', 'news@example.com');

        self::assertStringEndsWith('@example.com', $messageId);
    }
}
