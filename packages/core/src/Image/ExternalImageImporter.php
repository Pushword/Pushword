<?php

namespace Pushword\Core\Image;

use Cocur\Slugify\Slugify;
use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Service\SafeRemoteFileFetcher;
use Pushword\Core\Utils\Filepath;
use Pushword\Core\Utils\MediaRenamer;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

final readonly class ExternalImageImporter
{
    private const string CACHE_PREFIX = 'pushword-safe-image-';

    private MediaRenamer $renamer;

    public function __construct(
        private MediaStorageAdapter $mediaStorage,
        private ImageCacheGenerator $imageCacheGenerator,
        private string $mediaDir,
        private string $projectDir,
        private SafeRemoteFileFetcher $remoteFileFetcher,
        private Filesystem $filesystem = new Filesystem(),
    ) {
        $this->renamer = new MediaRenamer();
    }

    public function importExternal(
        string $image,
        string $name = '',
        string $slug = '',
        bool $hashInFilename = true,
    ): Media {
        $imageLocalImport = $this->cacheExternalImage($image);

        if (false === $imageLocalImport || ($imgSize = getimagesize($imageLocalImport)) === false) {
            throw new Exception('Image `'.$image.'` was not imported.');
        }

        $fileName = $this->generateFileName($image, $imgSize['mime'], '' !== $slug ? $slug : $name, $hashInFilename);

        $media = new Media();
        $media
            ->setProjectDir($this->projectDir)
            ->setStoreIn($this->mediaDir)
            ->setMimeType($imgSize['mime'])
            ->setSize((int) filesize($imageLocalImport))
            ->setDimensions([$imgSize[0], $imgSize[1]])
            ->setFileName($fileName)
            ->setSlug(Filepath::removeExtension($fileName))
            ->setAlt(str_replace(["\n", '"'], ' ', $name));

        $this->finishImportExternalByCopyingLocally($media, $imageLocalImport);
        $this->renamer->reset();

        return $media;
    }

    /**
     * @noRector
     */
    public function cacheExternalImage(string $src): false|string
    {
        $url = parse_url($src);
        if (
            false === $url
            || ! isset($url['scheme'], $url['host'])
            || ! \in_array(strtolower($url['scheme']), ['http', 'https'], true)
            || isset($url['user'])
            || isset($url['pass'])
        ) {
            return false;
        }

        // The namespace deliberately differs from the legacy cache, which could
        // contain entries fetched before private-network and local-file checks.
        $filePath = sys_get_temp_dir().'/'.self::CACHE_PREFIX.sha1($src);
        if ($this->filesystem->exists($filePath)) {
            return $filePath;
        }

        try {
            $content = $this->remoteFileFetcher->fetch($src);
        } catch (Throwable) {
            return false;
        }

        if (false === imagecreatefromstring($content)) {
            return false;
        }

        $this->filesystem->dumpFile($filePath, $content);

        return $filePath;
    }

    private function generateFileName(string $url, string $mimeType, string $slug, bool $hashInFilename): string
    {
        $slug = new Slugify()->slugify($slug);

        return ('' !== $slug ? $slug : pathinfo($url, \PATHINFO_BASENAME))
            .($hashInFilename ? '-'.substr(md5(sha1($url)), 0, 4) : '')
            .'.'.str_replace(['image/', 'jpeg'], ['', 'jpg'], $mimeType);
    }

    private function finishImportExternalByCopyingLocally(Media $media, string $imageLocalImport): void
    {
        if ($this->mediaStorage->fileExists($media->getFileName())) {
            $existingLocalPath = $this->mediaStorage->getLocalPath($media->getFileName());
            if (sha1_file($existingLocalPath) === sha1_file($imageLocalImport)) {
                return;
            }

            $this->renamer->rename($media);
            $this->finishImportExternalByCopyingLocally($media, $imageLocalImport);

            return;
        }

        $stream = fopen($imageLocalImport, 'r');
        if (false !== $stream) {
            $this->mediaStorage->writeStream($media->getFileName(), $stream);
            fclose($stream);
        }

        $this->imageCacheGenerator->generateCache($media);
    }
}
