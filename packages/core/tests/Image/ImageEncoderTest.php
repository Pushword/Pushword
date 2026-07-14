<?php

namespace Pushword\Core\Tests\Image;

use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Image\ImageEncoder;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final class ImageEncoderTest extends TestCase
{
    /**
     * A transient encoder failure yields an empty payload. Promoting it would
     * poison the cache with a 0-byte file that reads as fresh forever (broken
     * <img>, never regenerated), so it must be refused and leave no file behind.
     */
    public function testRefusesToWriteEmptyEncode(): void
    {
        $encoded = $this->createMock(EncodedImageInterface::class);
        $encoded->method('toString')->willReturn('');

        $image = $this->createMock(ImageInterface::class);
        $image->method('encodeUsingFormat')->willReturn($encoded);

        $output = sys_get_temp_dir().'/pw-encoder-empty-'.getmypid().'.webp';
        $fs = new Filesystem();
        $fs->remove($output);

        $thrown = false;

        try {
            new ImageEncoder()->encodeWebp($image, $output, 90);
        } catch (RuntimeException) {
            $thrown = true;
        }

        self::assertTrue($thrown, 'An empty encode must throw');
        self::assertFileDoesNotExist($output, 'An empty encode must not create the output file');
    }

    public function testWritesNonEmptyEncodeIntoPlace(): void
    {
        $encoded = $this->createMock(EncodedImageInterface::class);
        $encoded->method('toString')->willReturn('WEBP-BYTES');
        $encoded->method('save')->willReturnCallback(static function (string $path): void {
            file_put_contents($path, 'WEBP-BYTES');
        });

        $image = $this->createMock(ImageInterface::class);
        $image->method('encodeUsingFormat')->willReturn($encoded);

        $output = sys_get_temp_dir().'/pw-encoder-ok-'.getmypid().'.webp';
        $fs = new Filesystem();
        $fs->remove($output);

        new ImageEncoder()->encodeWebp($image, $output, 90);

        self::assertFileExists($output);
        self::assertSame('WEBP-BYTES', file_get_contents($output));

        $fs->remove($output);
    }

    /**
     * encodeOriginalToString() rewrites the master through the storage adapter,
     * so an empty payload must throw rather than return "" and blank the source.
     */
    public function testEncodeOriginalToStringRefusesEmpty(): void
    {
        $encoded = $this->createMock(EncodedImageInterface::class);
        $encoded->method('toString')->willReturn('');

        $image = $this->createMock(ImageInterface::class);
        $image->method('encode')->willReturn($encoded);

        $this->expectException(RuntimeException::class);
        new ImageEncoder()->encodeOriginalToString($image, 90, 'foo.jpg');
    }

    public function testEncodeOriginalToStringReturnsBytes(): void
    {
        $encoded = $this->createMock(EncodedImageInterface::class);
        $encoded->method('toString')->willReturn('JPG-BYTES');

        $image = $this->createMock(ImageInterface::class);
        $image->method('encode')->willReturn($encoded);

        self::assertSame('JPG-BYTES', new ImageEncoder()->encodeOriginalToString($image, 90, 'foo.jpg'));
    }

    /**
     * The 0-byte poison is transient (a fresh encode recovers), so a single
     * hiccup must be retried and heal within the same run rather than skipped.
     */
    public function testRetriesTransientEmptyEncode(): void
    {
        $empty = $this->createMock(EncodedImageInterface::class);
        $empty->method('toString')->willReturn('');
        $full = $this->createMock(EncodedImageInterface::class);
        $full->method('toString')->willReturn('WEBP-RECOVERED');

        $image = $this->createMock(ImageInterface::class);
        // First encode hiccups (empty), the retry recovers.
        $image->expects(self::exactly(2))->method('encodeUsingFormat')
            ->willReturnOnConsecutiveCalls($empty, $full);

        $output = sys_get_temp_dir().'/pw-encoder-retry-'.getmypid().'.webp';
        $fs = new Filesystem();
        $fs->remove($output);

        new ImageEncoder()->encodeWebp($image, $output, 90);

        self::assertFileExists($output);
        self::assertSame('WEBP-RECOVERED', file_get_contents($output));

        $fs->remove($output);
    }

    /**
     * Even with a valid encode, a failed disk write (here: a non-existent
     * target directory) must throw instead of silently reporting success.
     */
    public function testThrowsWhenWriteFails(): void
    {
        $encoded = $this->createMock(EncodedImageInterface::class);
        $encoded->method('toString')->willReturn('WEBP-BYTES');

        $image = $this->createMock(ImageInterface::class);
        $image->method('encodeUsingFormat')->willReturn($encoded);

        $output = sys_get_temp_dir().'/pw-encoder-missing-dir-'.getmypid().'/out.webp';

        $this->expectException(RuntimeException::class);
        new ImageEncoder()->encodeWebp($image, $output, 90);
    }
}
