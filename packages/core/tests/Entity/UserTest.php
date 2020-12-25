<?php

namespace Pushword\Core\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\User;

class UserTest extends TestCase
{
    public function testBasics(): void
    {
        $user = new User();
        self::assertEmpty($user->getEmail());

        $user->setEmail('test@example.tld');
        self::assertSame('test@example.tld', (string) $user);
    }
}
