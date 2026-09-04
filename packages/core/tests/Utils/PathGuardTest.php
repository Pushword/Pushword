<?php

namespace Pushword\Core\Tests\Utils;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\PathGuard;

final class PathGuardTest extends TestCase
{
    public function testJoinsNestedPathInsideBase(): void
    {
        self::assertSame('/srv/content/news/article.md', PathGuard::joinUnder('/srv/content', 'news', 'article.md'));
    }

    /** @return iterable<string, array{string}> */
    public static function escapingPaths(): iterable
    {
        yield 'parent traversal' => ['../../etc/passwd'];
        yield 'nested parent traversal' => ['news/../../../etc/passwd'];
    }

    #[DataProvider('escapingPaths')]
    public function testRejectsPathEscapingBase(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        PathGuard::joinUnder('/srv/content', $path);
    }
}
