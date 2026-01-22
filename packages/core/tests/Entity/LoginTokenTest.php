<?php

namespace Pushword\Core\Tests\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\LoginToken;
use Pushword\Core\Entity\User;

class LoginTokenTest extends TestCase
{
    public function testTokenCreation(): void
    {
        $user = new User();
        $user->email = 'test@example.tld';

        $token = new LoginToken($user, LoginToken::TYPE_LOGIN);

        self::assertSame($user, $token->user);
        self::assertSame(LoginToken::TYPE_LOGIN, $token->type);
        self::assertFalse($token->used);
        self::assertInstanceOf(DateTimeImmutable::class, $token->createdAt);
        self::assertInstanceOf(DateTimeImmutable::class, $token->expiresAt);
    }

    public function testTokenHashing(): void
    {
        $user = new User();
        $user->email = 'test@example.tld';

        $token = new LoginToken($user);
        $plainToken = 'my-secret-token';
        $token->setToken($plainToken);

        self::assertTrue($token->verifyToken($plainToken));
        self::assertFalse($token->verifyToken('wrong-token'));
        self::assertSame(hash('sha256', $plainToken), $token->getTokenHash());
    }

    public function testTokenValidity(): void
    {
        $user = new User();
        $user->email = 'test@example.tld';

        $token = new LoginToken($user);
        $token->setToken('test');

        // Token should be valid initially
        self::assertTrue($token->isValid());

        // Mark as used
        $token->markUsed();
        self::assertTrue($token->used);
        self::assertFalse($token->isValid());
    }

    public function testSetPasswordTokenType(): void
    {
        $user = new User();
        $user->email = 'test@example.tld';

        $token = new LoginToken($user, LoginToken::TYPE_SET_PASSWORD);

        self::assertSame(LoginToken::TYPE_SET_PASSWORD, $token->type);
    }

    public function testTokenTtl(): void
    {
        self::assertSame(3600, LoginToken::TTL_SECONDS);
    }

    public function testExpiresAtIsOneHourFromCreation(): void
    {
        $user = new User();
        $user->email = 'test@example.tld';

        $token = new LoginToken($user);

        $diff = $token->expiresAt->getTimestamp() - $token->createdAt->getTimestamp();
        self::assertSame(LoginToken::TTL_SECONDS, $diff);
    }
}
