<?php

namespace Pushword\Core\Tests\Utils;

use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\MediaFileName;
use Symfony\Component\HttpFoundation\File\File;

class MediaFileNameTest extends TestCase
{
    #[DataProvider('extractExtensionProvider')]
    public function testExtractExtension(string $input, string $expected): void
    {
        self::assertSame($expected, MediaFileName::extractExtension($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function extractExtensionProvider(): array
    {
        return [
            'standard jpg' => ['photo.jpg', '.jpg'],
            'standard png' => ['image.png', '.png'],
            'standard jpeg' => ['file.jpeg', '.jpeg'],
            'four char extension' => ['track.gpx', '.gpx'],
            'webp extension' => ['image.webp', '.webp'],
            'avif extension' => ['image.avif', '.avif'],
            'no extension' => ['filename', ''],
            'no dot' => ['filenamewithoutext', ''],
            'extension too long' => ['file.toolong', ''],
            'extension too short' => ['file.ab', ''],
            'multiple dots' => ['my.file.name.jpg', '.jpg'],
            'dot only' => ['.', ''],
            'hidden file no ext' => ['.htaccess', ''],
            'space before extension' => ['file .jpg', '.jpg'],  // space before dot still extracts extension
        ];
    }

    #[DataProvider('slugifyProvider')]
    public function testSlugify(string $input, string $expected): void
    {
        self::assertSame($expected, MediaFileName::slugify($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function slugifyProvider(): array
    {
        return [
            'already clean' => ['clean-file-name', 'clean-file-name'],
            'with spaces' => ['my file name', 'my-file-name'],
            'with uppercase' => ['MyFileName', 'myfilename'],
            'with accents' => ['éléphant', 'elephant'],
            'with special chars' => ['file@name#test', 'fileatname-test'],  // @ becomes 'at'
            'with registered trademark' => ['Brand® Product', 'brand-product'],
            'with trademark' => ['Brand™ Product', 'brand-product'],
            'with copyright' => ['Photo © Author', 'photo_author'],
            'with html copyright' => ['Photo &copy; Author', 'photo_author'],
            'preserve dots' => ['file.name', 'file.name'],
            'preserve underscores' => ['file_name', 'file_name'],
            'mixed special' => ['My Brand® Photo © 2024', 'my-brand-photo_2024'],
            'numeric copyright entity' => ['Image &#169; Owner', 'image_owner'],
            'hex copyright entity' => ['Image &#xA9; Owner', 'image_owner'],
        ];
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
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function slugifyPreservingExtensionProvider(): array
    {
        return [
            'with provided extension' => ['My Photo.jpg', '.jpg', 'my-photo.jpg'],
            'extract extension auto' => ['My Photo.jpg', '', 'my-photo.jpg'],
            'no extension in name' => ['My Photo', '.png', 'my-photo.png'],
            'accents with extension' => ['été à Paris.jpg', '.jpg', 'ete-a-paris.jpg'],
            'copyright in filename' => ['Photo © 2024.jpg', '', 'photo_2024.jpg'],
        ];
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
