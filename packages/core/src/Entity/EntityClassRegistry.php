<?php

namespace Pushword\Core\Entity;

final class EntityClassRegistry
{
    /** @var class-string<User> */
    private static string $userClass = User::class;

    /** @return class-string<User> */
    public static function getUserClass(): string
    {
        return self::$userClass;
    }

    /** @param class-string<User> $userClass */
    public static function configure(string $userClass): void
    {
        self::$userClass = $userClass;
    }
}
