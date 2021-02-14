<?php

namespace Pushword\Core\Service;

use Cocur\Slugify\Slugify;
use Exception;
use Intervention\Image\Image;
use Intervention\Image\ImageManager as InteventionImageManager;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Utils\Filepath;
use Pushword\Core\Utils\ImageOptimizer\OptimizerChainFactory;
use Spatie\ImageOptimizer\OptimizerChain;
use Symfony\Component\Filesystem\Filesystem;

class ImageManager
{
    private string $publicDir;
    private string $publicMediaDir;
    private string $mediaDir;
    private array $filterSets;
    private OptimizerChain $optimizer;
    private ?Image $lastThumb;
    private FileSystem $fileSystem;

    public function __construct(
        array $filterSets,
        string $publicDir,
        string $publicMediaDir,
        string $mediaDir
    ) {
        $this->filterSets = $filterSets;
        $this->publicDir = $publicDir;
        $this->publicMediaDir = $publicMediaDir;
        $this->fileSystem = new FileSystem();
        $this->mediaDir = $mediaDir;
        $this->optimizer = OptimizerChainFactory::create(); // todo make optimizer bin path configurable
    }

    public function setFilters(array $filters): void
    {
        $this->filterSets = $filters;
    }

    /**
     * @param MediaInterface|string $media
     */
    public function generateCache($media): void
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

    public function getLastThumb(): ?Image
    {
        return $this->lastThumb;
    }

    /**
     * @param MediaInterface|string $media
     * @param string|array          $filter
     */
    public function generateFilteredCache($media, $filter, ?Image $originalImage = null): Image
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

        foreach ($filters[$filterName]['filters'] as $filter => $parameters) {
            $parameters = \is_array($parameters) ? $parameters : [$parameters];
            $this->normalizeFilter($filter, $parameters);
            \call_user_func_array([$image, $filter], $parameters);
        }

        $quality = $filters[$filterName]['quality'] ?? 90;

        $this->createFilterDir(\dirname($this->getFilterPath($media, $filterName)));

        $image->save($this->getFilterPath($media, $filterName), $quality);
        $image->save($this->getFilterPath($media, $filterName, 'webp'), $quality, 'webp');

        $this->getFilterPath($media, $filterName);

        return $image;
    }

    private function createFilterDir(string $path): void
    {
        if (! file_exists($path)) {
            (new Filesystem())->mkdir($path);
        }
    }

    public function optimize(MediaInterface $media)
    {
        $filterNames = array_keys($this->filterSets);
        foreach ($filterNames as $filterName) {
            $this->optimizeFiltered($media, $filterName);
        }
    }

    private function optimizeFiltered(MediaInterface $media, string $filterName)
    {
        if (! file_exists($this->getFilterPath($media, $filterName)) || ! file_exists($this->getFilterPath($media, $filterName, 'webp'))) {
            $this->generateFilteredCache($media, $filterName);
        }

        $this->optimizer->optimize($this->getFilterPath($media, $filterName));
        $this->optimizer->optimize($this->getFilterPath($media, $filterName, 'webp'));
    }

    /**
     * Transform {$fiter}_notupsize in $fiter and add constrait->upsize()
     * or transform dowscale in resize with aspectRatio and upSize contraint.
     */
    private function normalizeFilter(string &$filter, array &$parameters): void
    {
        if ('downscale' == $filter) {
            $parameters[] = function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            };
            $filter = 'resize';
        }

        if (isset($parameters['constraint']) && \is_string($parameters['constraint'])) {
            $parameters[] = eval("return function(\$constraint) {{$parameters['constraint']}};");
            unset($parameters['constraint']);
        }
    }

    /**
     * @param MediaInterface|string $media
     */
    public function getFilterPath($media, string $filterName, ?string $extension = null, $browserPath = false): string
    {
        /** @var string $media */
        $media = $media instanceof MediaInterface ? $media->getMedia() : Filepath::filename($media);

        $fileName = null === $extension ? $media : Filepath::removeExtension($media).'.'.$extension;

        return ($browserPath ? '' : $this->publicDir).$this->publicMediaDir.'/'.$filterName.'/'.$fileName;
    }

    /**
     * @param MediaInterface|string $media
     */
    public function getBrowserPath($media, string $filterName = 'default', ?string $extension = null): string
    {
        return $this->getFilterPath($media, $filterName, $extension, true);
    }

    /**
     * @param MediaInterface|string $media string must be the accessible path (absolute) to the image file
     */
    private function getImage($media): Image
    {
        $path = $media instanceof MediaInterface ? $media->getPath() : $media;

        return (new InteventionImageManager())->make($path); // default driver GD
    }

    /**
     * @param MediaInterface|string $media
     */
    public function remove($media): void
    {
        /** @var string $media */
        $media = $media instanceof MediaInterface ? $media->getMedia() : Filepath::filename($media);

        $filterNames = array_keys($this->filterSets);
        foreach ($filterNames as $filterName) {
            @unlink($this->publicDir.$this->publicMediaDir.'/'.$filterName.'/'.$media);
        }
    }

    private function generateFileName(string $url, string $mimeType, string $slug, bool $hashInFilename): string
    {
        $slug = (new Slugify())->slugify($slug);

        return ($slug ?: pathinfo($url, \PATHINFO_BASENAME))
            .($hashInFilename ? '-'.substr(md5(sha1($url)), 0, 4) : '')
            .'.'.str_replace(['image/', 'jpeg'], ['', 'jpg'], $mimeType);
    }

    public function importExternal(string $image, string $name = '', string $slug = '', $hashInFilename = true): MediaInterface
    {
        $imageLocalImport = $this->cacheExternalImage($image);

        $imgSize = getimagesize($imageLocalImport);
        if (false === $imgSize) {
            throw new Exception('Image `'.$image.'` was not imported.');
        }

        $fileName = $this->generateFileName($image, $imgSize['mime'], $slug ?: $name, $hashInFilename);

        $newFilePath = $this->mediaDir.'/'.$fileName;

        $media = new Media();
        $media
                ->setStoreIn($this->mediaDir)
                ->setMimeType($imgSize['mime'])
                ->setSize(filesize($imageLocalImport))
                ->setDimensions([$imgSize[0], $imgSize[1]])
                ->setMedia($fileName)
                ->setName(str_replace(["\n", '"'], ' ', $name));

        if (! file_exists($newFilePath)) {
            $this->fileSystem->copy($imageLocalImport, $newFilePath);

            $this->generateCache($media);
        }

        return $media;
    }

    /**
     * Undocumented function.
     *
     * @return false|string
     */
    public function cacheExternalImage(string $src)
    {
        $filePath = sys_get_temp_dir().'/'.sha1($src);
        if (file_exists($filePath)) {
            return $filePath;
        }

        if (\function_exists('curl_init')) {
            $curl = curl_init($src);
            curl_setopt($curl, \CURLOPT_RETURNTRANSFER, 1);
            /** @var false|string $content */
            $content = curl_exec($curl);
            curl_close($curl);
        } else {
            $content = file_get_contents($src);
        }

        if (false === $content || false === imagecreatefromstring($content)) {
            return false;
        }

        $filePath = sys_get_temp_dir().'/'.sha1($src);
        if (false === file_put_contents($filePath, $content)) {
            throw new Exception('An error occured caching external resource in system tmp dir.');
        }

        return $filePath;
    }
}
