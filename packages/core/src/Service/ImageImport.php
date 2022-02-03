<?php

namespace Pushword\Core\Service;

use Cocur\Slugify\Slugify;
use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Utils\Filepath;
use Pushword\Core\Utils\MediaRenamer;

trait ImageImport
{
    private function generateFileName(string $url, string $mimeType, string $slug, bool $hashInFilename): string
    {
        $slug = (new Slugify())->slugify($slug);

        return ('' !== $slug ? $slug : pathinfo($url, \PATHINFO_BASENAME))
            .($hashInFilename ? '-'.\Safe\substr(md5(sha1($url)), 0, 4) : '')
            .'.'.str_replace(['image/', 'jpeg'], ['', 'jpg'], $mimeType);
    }

    private MediaRenamer $renamer;

    public function importExternal(
        string $image,
        string $name = '',
        string $slug = '',
        bool $hashInFilename = true
        //, $ifNameIsTaken = null
    ): MediaInterface {
        $this->renamer = new MediaRenamer();

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
                ->setSize(\Safe\filesize($imageLocalImport))
                ->setDimensions([$imgSize[0], $imgSize[1]])
                ->setMedia($fileName)
                ->setSlug(Filepath::removeExtension($fileName))
                ->setName(str_replace(["\n", '"'], ' ', $name));

        $this->finishImportExternalByCopyingLocally($media, $imageLocalImport);
        $this->renamer->reset();

        return $media;
    }

    private function finishImportExternalByCopyingLocally(MediaInterface $media, string $imageLocalImport): void
    {
        $newFilePath = $this->mediaDir.'/'.$media->getMedia();

        if (file_exists($newFilePath)) {
            if (sha1_file($newFilePath) !== sha1_file($imageLocalImport)) {
                // an image exist locally with same name/slug but is a different file
                $this->renamer->rename($media);
                $this->finishImportExternalByCopyingLocally($media, $imageLocalImport);

                return;
            }

            return; // same image is ever exist locally
        }

        $this->fileSystem->copy($imageLocalImport, $newFilePath);
        $this->generateCache($media);
    }

    abstract public function generateCache(MediaInterface $media): void;

    /**
     * @noRector
     *
     * @return false|string
     */
    public function cacheExternalImage(string $src)
    {
        $filePath = sys_get_temp_dir().'/'.sha1($src);
        if (file_exists($filePath)) {
            return $filePath;
        }

        if (! is_readable($src) && \function_exists('curl_init')) {
            $curl = curl_init($src);
            curl_setopt($curl, \CURLOPT_RETURNTRANSFER, 1); // @phpstan-ignore-line
            /** @var false|string $content */
            $content = curl_exec($curl); // @phpstan-ignore-line
            curl_close($curl); // @phpstan-ignore-line
        } else {
            $content = file_get_contents($src);
        }

        if (false === $content || false === imagecreatefromstring($content)) {
            return false;
        }

        \Safe\file_put_contents($filePath, $content);

        return $filePath;
    }
}
