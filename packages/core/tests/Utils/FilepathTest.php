<?php

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\Filepath;

class FilepathTest extends TestCase
{
    #[DataProvider('provideRemoveExtension')]
    public function testRemoveExtension(string $input, string $expected): void
    {
        self::assertSame($expected, Filepath::removeExtension($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRemoveExtension(): iterable
    {
        yield 'standard extension' => ['photo.jpg', 'photo'];
        yield 'no extension' => ['readme', 'readme'];
        yield 'hidden file' => ['.gitignore', '.gitignore'];
        yield 'long extension kept' => ['archive.tar.gz', 'archive.tar'];
        yield 'webp extension' => ['image.webp', 'image'];
        yield 'jpeg extension' => ['image.jpeg', 'image'];
        yield 'extension too long (6 chars)' => ['file.backup', 'file.backup'];
        yield 'path with directory' => ['/var/www/photo.png', '/var/www/photo'];
    }

    #[DataProvider('provideFilename')]
    public function testFilename(string $input, string $expected): void
    {
        self::assertSame($expected, Filepath::filename($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFilename(): iterable
    {
        yield 'with path' => ['/var/www/photo.jpg', 'photo.jpg'];
        yield 'without path' => ['photo.jpg', 'photo.jpg'];
        yield 'nested path' => ['/a/b/c/file.txt', 'file.txt'];
        yield 'trailing slash' => ['/var/www/', ''];
    }
}
