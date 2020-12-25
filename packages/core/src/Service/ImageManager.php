<?php

namespace Pushword\Core\Service;

use Cocur\Slugify\Slugify;
use Exception;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\ImageManager as InteventionImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Utils\Filepath;
use Pushword\Core\Utils\ImageOptimizer\OptimizerChainFactory;
use Pushword\Core\Utils\MediaRenamer;

use function Safe\file_put_contents;
use function Safe\filesize;

use Spatie\ImageOptimizer\OptimizerChain;
use Symfony\Component\Filesystem\Filesystem;

final class ImageManager
{
    private readonly OptimizerChain $optimizer;

    private ?ImageInterface $lastThumb = null;

    private readonly Filesystem $fileSystem;

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    public function __construct(
        private array $filterSets,
        private readonly string $publicDir,
        private readonly string $projectDir,
        private readonly string $publicMediaDir,
        private readonly string $mediaDir
    ) {
        $this->fileSystem = new Filesystem();
        $this->optimizer = OptimizerChainFactory::create(); // t o d o make optimizer bin path configurable
        $this->renamer = new MediaRenamer();
    }

    /**
     * @param array<string, array<string, mixed>> $filters
     */
    public function setFilters(array $filters): void
    {
        $this->filterSets = $filters;
    }

    public function isImage(Media $media): bool
    {
        return $media->isImage();
    }

    public function generateCache(Media $media): void
    {
        $image = $this->getImage($media);

        $filterNames = array_keys($this->filterSets);
        foreach ($filterNames as $filterName) {
            $lastImg = $this->generateFilteredCache($media, $filterName, $image);
            if ('thumb' == $filterName) {
                $this->lastThumb = $lastImg;
            }
        }

        exec('cd ../ && php bin/console pushword:image:optimize '.$media->getMedia().' > /dev/null 2>/dev/null &');
    }

    public function getLastThumb(): ?ImageInterface
    {
        return $this->lastThumb;
    }

    /**
     * @param array<string, mixed>|string $filter
     */
    public function generateFilteredCache(Media|string $media, array|string $filter, ?ImageInterface $originalImage = null): ImageInterface
    {
        if (\is_array($filter)) {
            $filterName = array_keys($filter)[0];
            $filters = $filter;
        } else {
            $filters = $this->filterSets;
            $filterName = $filter;
        }

        $image = null === $originalImage ? $this->getImage($media)
            : ('default' == $filterName ? $originalImage : clone $originalImage); // don't clone if default for speed perf

        /** @psalm-suppress all */
        foreach ($filters[$filterName]['filters'] as $filter => $parameters) { // @phpstan-ignore-line
            $parameters = \is_array($parameters) ? $parameters : [$parameters];
            \call_user_func_array([$image, $filter], $parameters); // @phpstan-ignore-line
        }

        /** @psalm-suppress all */
        $quality = (int) ($filters[$filterName]['quality'] ?? 90); // @phpstan-ignore-line

        $this->createFilterDir(\dirname($this->getFilterPath($media,  $filterName)));

        $image->encode(new AutoEncoder(quality: $quality))->save($this->getFilterPath($media, $filterName));
        $image->toWebp($quality)->save($this->getFilterPath($media, $filterName, 'webp'));

        $this->getFilterPath($media, $filterName);

        return $image;
    }

    private function createFilterDir(string $path): void
    {
        if (! file_exists($path)) {
            (new Filesystem())->mkdir($path);
        }
    }

    public function optimize(Media $media): void
    {
        $filterNames = array_keys($this->filterSets);
        foreach ($filterNames as $filterName) {
            $this->optimizeFiltered($media, $filterName);
        }
    }

    private function optimizeFiltered(Media $media, string $filterName): void
    {
        if (! file_exists($this->getFilterPath($media, $filterName)) || ! file_exists($this->getFilterPath($media, $filterName, 'webp'))) {
            $this->generateFilteredCache($media, $filterName);
        }

        $this->optimizer->optimize($this->getFilterPath($media, $filterName));
        $this->optimizer->optimize($this->getFilterPath($media, $filterName, 'webp'));
    }

    public function getFilterPath(Media|string $media, string $filterName, ?string $extension = null, bool $browserPath = false): string
    {
        $media = $media instanceof Media ? $media->getMedia() : Filepath::filename($media);

        $fileName = null === $extension ? $media : Filepath::removeExtension($media).'.'.$extension;

        return ($browserPath ? '' : $this->publicDir).'/'.$this->publicMediaDir.'/'.$filterName.'/'.$fileName;
    }

    public function getBrowserPath(Media|string $media, string $filterName = 'default', ?string $extension = null): string
    {
        return $this->getFilterPath($media, $filterName, $extension, true);
    }

    /**
     * @return int[] index 0 contains width, index 1 height
     */
    public function getDimensions(Media|string $media): array
    {
        $path = $this->getFilterPath($media, 'xs');

        $size = @getimagesize($path);
        if (false === $size) {
            throw new Exception('`'.$path.'` not found');
        }

        return [$size[0], $size[1]];
    }

    /**
     * @param Media|string $media string must be the accessible path (absolute) to the image file
     */
    private function getImage(Media|string $media): ImageInterface
    {
        $path = $media instanceof Media ? $media->getPath() : $media;

        try {
            return InteventionImageManager::gd()->read($path); // default driver GD
        } catch (Exception) {
            throw new Exception($path);
        }
    }

    public function remove(Media|string $media): void
    {
        $media = $media instanceof Media ? $media->getMedia() : Filepath::filename($media);

        $filterNames = array_keys($this->filterSets);
        foreach ($filterNames as $filterName) {
            @unlink($this->publicDir.'/'.$this->publicMediaDir.'/'.$filterName.'/'.$media);
        }
    }

    // ImageImport
    private readonly MediaRenamer $renamer;

    private function generateFileName(string $url, string $mimeType, string $slug, bool $hashInFilename): string
    {
        $slug = (new Slugify())->slugify($slug);

        return ('' !== $slug ? $slug : pathinfo($url, \PATHINFO_BASENAME))
            .($hashInFilename ? '-'.substr(md5(sha1($url)), 0, 4) : '')
            .'.'.str_replace(['image/', 'jpeg'], ['', 'jpg'], $mimeType);
    }

    public function importExternal(
        string $image,
        string $name = '',
        string $slug = '',
        bool $hashInFilename = true
        // , $ifNameIsTaken = null
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
                ->setMedia($fileName)
                ->setSlug(Filepath::removeExtension($fileName))
                ->setName(str_replace(["\n", '"'], ' ', $name));

        $this->finishImportExternalByCopyingLocally($media, $imageLocalImport);
        $this->renamer->reset();

        return $media;
    }

    private function finishImportExternalByCopyingLocally(Media $media, string $imageLocalImport): void
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
            curl_setopt($curl, \CURLOPT_RETURNTRANSFER, 1); // @phpstan-ignore-line
            /** @var false|string $content */
            $content = curl_exec($curl); // @phpstan-ignore-line
            curl_close($curl); // @phpstan-ignore-line
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
}
