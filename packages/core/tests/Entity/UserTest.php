<?php

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

    public function testBlankPlainPasswordPreservesExistingHash(): void
    {
        $user = new User()->setPassword('existing-hash');

        $user->setPlainPassword('');

        self::assertSame('existing-hash', $user->getPassword());
        self::assertSame('', $user->getPlainPassword());
    }

    public function testEmailRemainsSecurityIdentifierWhenUsernameExists(): void
    {
        $user = new User();
        $user->email = 'remember-me@example.tld';
        $user->username = 'Display name';

        self::assertSame('remember-me@example.tld', $user->getUserIdentifier());
        self::assertSame('Display name', $user->getUsername());
    }

    public function testTemporaryPasswordRestrictsRolesUntilChanged(): void
    {
        $user = new User()->setRoles([User::ROLE_SUPER_ADMIN])->requirePasswordChange();

        self::assertSame([User::ROLE_PASSWORD_CHANGE], $user->getRoles());
        self::assertTrue($user->requiresPasswordChange());

        $user->completePasswordChange();

        self::assertContains(User::ROLE_SUPER_ADMIN, $user->getRoles());
        self::assertFalse($user->requiresPasswordChange());
    }
}
