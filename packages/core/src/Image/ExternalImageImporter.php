<?php

namespace Pushword\Core\Image;

use Cocur\Slugify\Slugify;
use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Utils\Filepath;
use Pushword\Core\Utils\MediaRenamer;

use function Safe\file_put_contents;
use function Safe\filesize;

final readonly class ExternalImageImporter
{
    private MediaRenamer $renamer;

    public function __construct(
        private MediaStorageAdapter $mediaStorage,
        private ThumbnailGenerator $thumbnailGenerator,
        private string $mediaDir,
        private string $projectDir,
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
            ->setSize(filesize($imageLocalImport))
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
        $filePath = sys_get_temp_dir().'/'.sha1($src);
        if (file_exists($filePath)) {
            return $filePath;
        }

        if (! is_readable($src) && \function_exists('curl_init')) {
            $curl = curl_init($src);
            curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
            /** @var false|string $content */
            $content = curl_exec($curl);
            unset($curl);
        } else {
            $content = file_get_contents($src);
        }

        if (false === $content) {
            return false;
        }

        if (false === imagecreatefromstring($content)) {
            return false;
        }

        file_put_contents($filePath, $content);

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

        $this->thumbnailGenerator->generateCache($media);
    }
}
