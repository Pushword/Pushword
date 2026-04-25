<?php

namespace Pushword\Core\Tests\Utils;

use Exception;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\MediaFileName;
use Symfony\Component\HttpFoundation\File\File;

final class MediaFileNameTest extends TestCase
{
    #[DataProvider('extractExtensionProvider')]
    public function testExtractExtension(string $input, string $expected): void
    {
        self::assertSame($expected, MediaFileName::extractExtension($input));
    }

    /**
     * @return Iterator<string, array{string, string}>
     */
    public static function extractExtensionProvider(): Iterator
    {
        yield 'standard jpg' => ['photo.jpg', '.jpg'];
        yield 'standard png' => ['image.png', '.png'];
        yield 'standard jpeg' => ['file.jpeg', '.jpeg'];
        yield 'four char extension' => ['track.gpx', '.gpx'];
        yield 'webp extension' => ['image.webp', '.webp'];
        yield 'no extension' => ['filename', ''];
        yield 'no dot' => ['filenamewithoutext', ''];
        yield 'extension too long' => ['file.toolong', ''];
        yield 'extension too short' => ['file.ab', ''];
        yield 'multiple dots' => ['my.file.name.jpg', '.jpg'];
        yield 'dot only' => ['.', ''];
        yield 'hidden file no ext' => ['.htaccess', ''];
        yield 'space before extension' => ['file .jpg', '.jpg'];
    }

    #[DataProvider('slugifyProvider')]
    public function testSlugify(string $input, string $expected): void
    {
        self::assertSame($expected, MediaFileName::slugify($input));
    }

    /**
     * @return Iterator<string, array{string, string}>
     */
    public static function slugifyProvider(): Iterator
    {
        yield 'already clean' => ['clean-file-name', 'clean-file-name'];
        yield 'with spaces' => ['my file name', 'my-file-name'];
        yield 'with uppercase' => ['MyFileName', 'myfilename'];
        yield 'with accents' => ['éléphant', 'elephant'];
        yield 'with special chars' => ['file@name#test', 'fileatname-test'];
        // @ becomes 'at'
        yield 'with registered trademark' => ['Brand® Product', 'brand-product'];
        yield 'with trademark' => ['Brand™ Product', 'brand-product'];
        yield 'with copyright' => ['Photo © Author', 'photo_author'];
        yield 'with html copyright' => ['Photo &copy; Author', 'photo_author'];
        yield 'preserve dots' => ['file.name', 'file.name'];
        yield 'preserve underscores' => ['file_name', 'file_name'];
        yield 'mixed special' => ['My Brand® Photo © 2024', 'my-brand-photo_2024'];
        yield 'numeric copyright entity' => ['Image &#169; Owner', 'image_owner'];
        yield 'hex copyright entity' => ['Image &#xA9; Owner', 'image_owner'];
        yield 'text copyright' => ['Photo (c) Author', 'photo_author'];
    }

    #[DataProvider('slugifyPreservingExtensionProvider')]
    public function testSlugifyPreservingExtension(
        string $filename,
        string $extension,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            MediaFileName::slugifyPreservingExtension($filename, $extension),
        );
    }

    /**
     * @return Iterator<string, array{string, string, string}>
     */
    public static function slugifyPreservingExtensionProvider(): Iterator
    {
        yield 'with provided extension' => ['My Photo.jpg', '.jpg', 'my-photo.jpg'];
        yield 'extract extension auto' => ['My Photo.jpg', '', 'my-photo.jpg'];
        yield 'no extension in name' => ['My Photo', '.png', 'my-photo.png'];
        yield 'accents with extension' => ['été à Paris.jpg', '.jpg', 'ete-a-paris.jpg'];
        yield 'copyright in filename' => ['Photo © 2024.jpg', '', 'photo_2024.jpg'];
    }

    public function testFixExtensionForGpx(): void
    {
        // GPX files detected as text/plain return .txt, should be fixed to .gpx
        self::assertSame('.gpx', MediaFileName::fixExtension('.txt', 'track.gpx'));
        self::assertSame('.txt', MediaFileName::fixExtension('.txt', 'document.txt'));
        self::assertSame('.jpg', MediaFileName::fixExtension('.jpg', 'photo.jpg'));
    }

    public function testNormalizeFromString(): void
    {
        self::assertSame(
            'my-photo.jpg',
            MediaFileName::normalizeFromString('My Photo.jpg'),
        );
        self::assertSame(
            'summer-vacation-2024.png',
            MediaFileName::normalizeFromString('Summer Vacation 2024.png'),
        );
    }

    public function testNormalizeFromStringThrowsOnEmpty(): void
    {
        $this->expectException(Exception::class);
        MediaFileName::normalizeFromString('');
    }

    public function testExtractExtensionFromFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, 'test content');

        try {
            $file = new File($tempFile);
            // File without proper extension, guessExtension returns txt for plain text
            $extension = MediaFileName::extractExtensionFromFile($file, 'myfile.txt');
            self::assertSame('.txt', $extension);
        } finally {
            unlink($tempFile);
        }
    }

    public function testNormalizeWithFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, 'test content');

        try {
            $file = new File($tempFile);
            $normalized = MediaFileName::normalize($file, 'My Document.txt');
            self::assertSame('my-document.txt', $normalized);
        } finally {
            unlink($tempFile);
        }
    }
}
