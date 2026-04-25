<?php

namespace Pushword\Core\Tests\Cache;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\PageCacheSuppressor;
use RuntimeException;

final class PageCacheSuppressorTest extends TestCase
{
    public function testNotSuppressedByDefault(): void
    {
        $suppressor = new PageCacheSuppressor();

        self::assertFalse($suppressor->isSuppressed());
    }

    public function testSuppressedInsideCallback(): void
    {
        $suppressor = new PageCacheSuppressor();

        $suppressor->suppress(static function () use ($suppressor): void {
            self::assertTrue($suppressor->isSuppressed());
        });
    }

    public function testNotSuppressedAfterCallback(): void
    {
        $suppressor = new PageCacheSuppressor();

        $suppressor->suppress(static fn (): null => null);

        self::assertFalse($suppressor->isSuppressed());
    }

    public function testNestedSuppressRemainsSuppressedUntilOuterExits(): void
    {
        $suppressor = new PageCacheSuppressor();

        $suppressor->suppress(static function () use ($suppressor): void {
            $suppressor->suppress(static fn (): null => null);

            self::assertTrue($suppressor->isSuppressed(), 'Should still be suppressed in outer after inner exits');
        });

        self::assertFalse($suppressor->isSuppressed());
    }

    public function testSuppressedReleasedOnException(): void
    {
        $suppressor = new PageCacheSuppressor();

        try {
            $suppressor->suppress(static function (): never {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        self::assertFalse($suppressor->isSuppressed(), 'Must release suppression even when callback throws');
    }

    public function testReturnValuePassedThrough(): void
    {
        $suppressor = new PageCacheSuppressor();

        $result = $suppressor->suppress(static fn (): string => 'hello');

        self::assertSame('hello', $result); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }
}
