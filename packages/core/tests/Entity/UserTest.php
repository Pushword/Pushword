<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\User;

final class UserTest extends TestCase
{
    public function testBasics(): void
    {
        $user = new User();
        self::assertEmpty($user->email);

        $user->email = 'test@example.tld';
        self::assertSame('test@example.tld', (string) $user);
    }
}
