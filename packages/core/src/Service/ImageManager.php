<?php

namespace Pushword\Core\Service;

use Exception;
use Intervention\Image\Image;
use Intervention\Image\ImageManager as InteventionImageManager;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Utils\Filepath;
use Pushword\Core\Utils\ImageOptimizer\OptimizerChainFactory;
use Spatie\ImageOptimizer\OptimizerChain;
use Symfony\Component\Filesystem\Filesystem;

final class ImageManager
{
    use ImageImport;

    private OptimizerChain $optimizer;

    private ?Image $lastThumb = null;

    private FileSystem $fileSystem;

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    public function __construct(
        private array $filterSets,
        private string $publicDir,
        private string $projectDir,
        private string $publicMediaDir,
        private string $mediaDir
    ) {
        $this->fileSystem = new FileSystem();
        $this->optimizer = OptimizerChainFactory::create(); // t o d o make optimizer bin path configurable
    }

    /**
     * @param array<string, array<string, mixed>> $filters
     */
    public function setFilters(array $filters): void
    {
        $this->filterSets = $filters;
    }

    public function isImage(MediaInterface $media): bool
    {
        return $media->isImage();
    }

    public function generateCache(MediaInterface $media): void
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
     * @param array<string, mixed>|string $filter
     */
    public function generateFilteredCache(MediaInterface|string $media, array|string $filter, ?Image $originalImage = null): Image
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

        foreach ($filters[$filterName]['filters'] as $filter => $parameters) { // @phpstan-ignore-line
            $parameters = \is_array($parameters) ? $parameters : [$parameters];
            $this->normalizeFilter($filter, $parameters);
            \call_user_func_array([$image, $filter], $parameters);  // @phpstan-ignore-line
        }

        /**
         * @psalm-suppress RedundantCondition
         *
         * @psam-suppress TypeDoesNotContainNull
         */
        $quality = (int) (! isset($filters[$filterName]['quality']) ? 90 : $filters[$filterName]['quality']); // @phpstan-ignore-line

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

    public function optimize(MediaInterface $media): void
    {
        $filterNames = array_keys($this->filterSets);
        foreach ($filterNames as $filterName) {
            $this->optimizeFiltered($media, $filterName);
        }
    }

    private function optimizeFiltered(MediaInterface $media, string $filterName): void
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
     *
     * @param array<mixed> $parameters
     */
    private function normalizeFilter(string &$filter, array &$parameters): void
    {
        if ('downscale' == $filter) {
            $parameters[] = function ($constraint): void {
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

    public function getFilterPath(MediaInterface|string $media, string $filterName, ?string $extension = null, bool $browserPath = false): string
    {
        /** @var string $media */
        $media = $media instanceof MediaInterface ? $media->getMedia() : Filepath::filename($media);

        $fileName = null === $extension ? $media : Filepath::removeExtension($media).'.'.$extension;

        return ($browserPath ? '' : $this->publicDir).'/'.$this->publicMediaDir.'/'.$filterName.'/'.$fileName;
    }

    public function getBrowserPath(MediaInterface|string $media, string $filterName = 'default', ?string $extension = null): string
    {
        return $this->getFilterPath($media, $filterName, $extension, true);
    }

    /**
     * @return int[] index 0 contains width, index 1 height
     */
    public function getDimensions(MediaInterface|string $media): array
    {
        $path = $this->getFilterPath($media, 'xs');

        $size = @getimagesize($path);
        if (false === $size) {
            throw new Exception('`'.$path.'` not found');
        }

        return [$size[0], $size[1]];
    }

    /**
     * @param MediaInterface|string $media string must be the accessible path (absolute) to the image file
     */
    private function getImage(MediaInterface|string $media): Image
    {
        $path = $media instanceof MediaInterface ? $media->getPath() : $media;

        try {
            return (new InteventionImageManager())->make($path); // default driver GD
        } catch (Exception) {
            throw new Exception($media->getId().': '.$path);
        }
    }

    public function remove(MediaInterface|string $media): void
    {
        /** @var string $media */
        $media = $media instanceof MediaInterface ? $media->getMedia() : Filepath::filename($media);

        $filterNames = array_keys($this->filterSets);
        foreach ($filterNames as $filterName) {
            @unlink($this->publicDir.'/'.$this->publicMediaDir.'/'.$filterName.'/'.$media);
        }
    }
}
