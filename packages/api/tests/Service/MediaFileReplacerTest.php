<?php

declare(strict_types=1);

namespace Pushword\Api\Tests\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Pushword\Api\Service\MediaFileReplacer;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Service\PdfOptimizer;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaFileReplacerTest extends TestCase
{
    /** @return iterable<string, array{bool, string, bool}> */
    public static function replacements(): iterable
    {
        foreach ([true, false] as $local) {
            foreach (['jpg', 'txt', 'pdf'] as $extension) {
                yield ($local ? 'local' : 'remote').' '.$extension => [$local, $extension, false];
            }
        }

        yield 'large image with deferred preview' => [true, 'jpg', true];
    }

    #[DataProvider('replacements')]
    public function testReplacementRefreshesStorageAndSchedulesDerivatives(bool $local, string $extension, bool $deferPreview): void
    {
        $fs = new LocalFilesystem();
        $dir = sys_get_temp_dir().'/media-replacement-'.uniqid();
        $fileName = basename($dir).'.'.$extension;
        $fs->mkdir($dir.'/masters');
        $storage = new MediaStorageAdapter(new Filesystem(
            $local ? new LocalFilesystemAdapter($dir.'/masters') : new InMemoryFilesystemAdapter(),
        ), $dir.'/masters', $local);
        $storage->write($fileName, 'previous bytes');

        $localPath = $storage->getLocalPath($fileName);
        self::assertSame('previous bytes', file_get_contents($localPath));
        $initialMemoryLimit = ini_get('memory_limit');

        try {
            $media = new Media();
            $media->setFileName($fileName)->setMimeType(match ($extension) {
                'jpg' => 'image/jpeg',
                'pdf' => 'application/pdf',
                default => 'text/plain',
            });

            $uploadPath = $dir.'/upload.'.$extension;
            $width = $deferPreview ? 4000 : 4;
            $height = $deferPreview ? 4000 : 7;
            if ('jpg' === $extension) {
                $image = imagecreatetruecolor($width, $height);
                self::assertNotFalse($image);
                imagejpeg($image, $uploadPath);
                unset($image);
                $media->setDimensions([2, 3])->setMainColor('#abcdef');
            } else {
                $fs->dumpFile($uploadPath, 'pdf' === $extension ? "%PDF-1.4\nnew content\n%%EOF\n" : 'new content');
            }

            $bytes = file_get_contents($uploadPath);
            self::assertIsString($bytes);
            $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
            if ('txt' === $extension) {
                $dispatcher->expects(self::never())->method('dispatch');
            } else {
                $command = 'jpg' === $extension ? 'pw:image:cache' : 'pw:pdf:optimize';
                $dispatcher->expects(self::once())->method('dispatch')->with(
                    self::anything(),
                    ['php', 'bin/console', $command, $fileName],
                    $command,
                );
            }

            $cache = new ImageCacheManager(['md' => ['quality' => 80, 'filters' => []]], 'media', $dir.'/cache', $storage);
            $preview = $cache->getFilterPath($media, 'md');
            $webp = $cache->getFilterPath($media, 'md', 'webp');
            $fs->dumpFile($preview, 'old preview');
            $fs->dumpFile($webp, 'old webp');
            $generator = new ImageCacheGenerator(new ImageReader($storage, 'gd'), new ImageEncoder(), $cache, $dispatcher, $storage);
            $replacer = new MediaFileReplacer($storage, $cache, $generator, new PdfOptimizer($storage, $dispatcher, new NullLogger()));

            if ($deferPreview) {
                ini_set('memory_limit', (string) (memory_get_usage(true) + 32 * 1024 ** 2));
            }

            $replacer->replace($media, new UploadedFile($uploadPath, 'upload.'.$extension, test: true));

            self::assertSame($bytes, $storage->read($fileName));
            self::assertSame($bytes, file_get_contents($localPath), 'the local copy of a remote master must be refreshed');
            self::assertSame(sha1($bytes, true), $media->getHash());
            self::assertSame(sha1($bytes, true), $media->getOriginalHash());
            self::assertSame(\strlen($bytes), $media->size);
            self::assertFileDoesNotExist($webp);
            if ('jpg' === $extension) {
                self::assertSame($bytes, file_get_contents($preview));
                self::assertSame($width, $media->getWidth());
                self::assertSame($height, $media->getHeight());
                if ($deferPreview) {
                    self::assertNull($media->getMainColor(), 'the previous image color must not survive a deferred decode');

                    return;
                }

                self::assertNotNull($media->getMainColor());
                // The queued command uses this same generator to build the remaining variants.
                $generator->generateFilteredCache($media, 'md');
                self::assertFileExists($webp);
                $dimensions = getimagesize($webp);
                self::assertIsArray($dimensions);
                self::assertSame([4, 7], [$dimensions[0], $dimensions[1]]);
            } elseif ($local) {
                self::assertSame($bytes, file_get_contents($dir.'/cache/'.$fileName));
            }
        } finally {
            ini_set('memory_limit', $initialMemoryLimit);
            $fs->remove([$dir, $localPath]);
        }
    }
}
