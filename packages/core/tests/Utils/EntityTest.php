<?php

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Core\Utils\Entity;

final class EntityTest extends TestCase
{
    public function testPublicPropertyIsReadableAndWritable(): void
    {
        $page = new Page();
        self::assertTrue(Entity::isPubliclyReadableProperty($page, 'redirectFrom'));
        self::assertTrue(Entity::isPubliclyWritableProperty($page, 'redirectFrom'));
    }

    public function testPrivateSetPropertyIsReadableButNotWritable(): void
    {
        $page = new Page();
        self::assertTrue(Entity::isPubliclyReadableProperty($page, 'id'));
        self::assertFalse(Entity::isPubliclyWritableProperty($page, 'id'));
    }

    public function testProtectedPropertyIsNeither(): void
    {
        $page = new Page();
        self::assertFalse(Entity::isPubliclyReadableProperty($page, 'tags'));
        self::assertFalse(Entity::isPubliclyWritableProperty($page, 'tags'));
    }

    public function testUnknownPropertyIsNeither(): void
    {
        $page = new Page();
        self::assertFalse(Entity::isPubliclyReadableProperty($page, 'doesNotExist'));
        self::assertFalse(Entity::isPubliclyWritableProperty($page, 'doesNotExist'));
    }

    public function testReadonlyPropertyIsNotWritable(): void
    {
        $object = new readonly class {
            public function __construct(
                public string $frozen = 'x',
            ) {
            }
        };

        self::assertTrue(Entity::isPubliclyReadableProperty($object, 'frozen'));
        self::assertFalse(Entity::isPubliclyWritableProperty($object, 'frozen'));
    }
}
