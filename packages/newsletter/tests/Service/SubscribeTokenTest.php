<?php

namespace Pushword\Newsletter\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Service\SubscribeToken;

final class SubscribeTokenTest extends TestCase
{
    private const string SECRET = 'a-secret-nobody-spraying-the-endpoint-has';

    /** A token as this install would have issued it, for an expiry we choose. */
    private function forge(int $expiry): string
    {
        return $expiry.'.'.mb_substr(hash_hmac('sha256', (string) $expiry, self::SECRET), 0, 32);
    }

    public function testATokenItIssuedIsAccepted(): void
    {
        $subscribeToken = new SubscribeToken(self::SECRET);

        self::assertTrue($subscribeToken->isValid($subscribeToken->issue()));
    }

    /** The whole point: holding one has to mean having fetched a form. */
    public function testATokenSignedWithAnotherSecretIsRefused(): void
    {
        $elsewhere = new SubscribeToken('the-secret-of-some-other-install');

        self::assertFalse(new SubscribeToken(self::SECRET)->isValid($elsewhere->issue()));
    }

    public function testATokenPastItsExpiryIsRefused(): void
    {
        self::assertFalse(new SubscribeToken(self::SECRET)->isValid($this->forge(time() - 1)));
    }

    /**
     * The expiry is inside the signed material, so reading a token and writing a
     * later date on it is not a way to keep one alive.
     */
    public function testPushingTheExpiryOutBreaksTheSignature(): void
    {
        $subscribeToken = new SubscribeToken(self::SECRET);
        [, $signature] = explode('.', $subscribeToken->issue());

        self::assertFalse($subscribeToken->isValid((time() + 864000).'.'.$signature));
    }

    public function testAnythingThatIsNotATokenIsRefused(): void
    {
        $subscribeToken = new SubscribeToken(self::SECRET);

        self::assertFalse($subscribeToken->isValid(''));
        self::assertFalse($subscribeToken->isValid('not-the-one'));
    }
}
