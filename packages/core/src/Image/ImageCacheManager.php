<?php

namespace Pushword\Core\Image;

use Exception;
use Pushword\Core\Entity\Dimensions;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Utils\Filepath;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class ImageCacheManager
{
    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    public function __construct(
        private array $filterSets,
        private readonly string $publicDir,
        private readonly string $publicMediaDir,
        private readonly MediaStorageAdapter $mediaStorage,
    ) {
    }

    public function getFilterPath(Media|string $media, string $filterName, ?string $extension = null, bool $browserPath = false): string
    {
        $media = $media instanceof Media ? $media->getFileName() : Filepath::filename($media);
        $fileName = null === $extension ? $media : Filepath::removeExtension($media).'.'.$extension;

        return ($browserPath ? '' : $this->publicDir).'/'.$this->publicMediaDir.'/'.$filterName.'/'.$fileName;
    }

    #[AsTwigFilter('image')]
    public function getBrowserPath(
        Media|string $media,
        string $filterName = 'default',
        ?string $extension = null,
        bool $checkFileExists = false,
    ): string {
        $mediaFileName = $media instanceof Media ? $media->getFileName() : Filepath::filename($media);
        if (str_ends_with(strtolower($mediaFileName), '.svg')) {
            return '/'.$this->publicMediaDir.'/'.$mediaFileName;
        }

        if (null !== $extension) {
            return $this->getFilterPath($media, $filterName, $extension, true);
        }

        /** @var string[] $formats */
        $formats = $this->filterSets[$filterName]['formats'] ?? ['webp', 'original'];

        if (\in_array('webp', $formats, true)) {
            $webpPath = $this->getFilterPath($media, $filterName, 'webp');
            if (! $checkFileExists || file_exists($webpPath)) {
                return $this->getFilterPath($media, $filterName, 'webp', true);
            }
        }

        if (\in_array('original', $formats, true)) {
            $originalPath = $this->getFilterPath($media, $filterName);
            if (! $checkFileExists || file_exists($originalPath)) {
                return $this->getFilterPath($media, $filterName, null, true);
            }
        }

        return $this->getFilterPath($media, $filterName, null, true);
    }

    #[AsTwigFunction('image_dimensions')]
    public function getDimensions(Media|string $media): Dimensions
    {
        $path = $this->getFilterPath($media, 'xs');
        $size = @getimagesize($path);
        if (false === $size) {
            throw new Exception('`'.$path.'` not found');
        }

        return new Dimensions($size[0], $size[1]);
    }

    /**
     * Returns the preferred modern image format for a given filter.
     */
    #[AsTwigFunction('preferred_modern_format')]
    public function getPreferredModernFormat(string $filterName = 'xs'): ?string
    {
        /** @var string[] $formats */
        $formats = $this->filterSets[$filterName]['formats'] ?? ['original', 'webp'];

        if (\in_array('webp', $formats, true)) {
            return 'webp';
        }

        return null;
    }

    public function remove(Media|string $media): void
    {
        $mediaFileName = $media instanceof Media ? $media->getFileName() : Filepath::filename($media);
        $mediaBase = Filepath::removeExtension($mediaFileName);

        foreach (array_keys($this->filterSets) as $filterName) {
            $path = $this->publicDir.'/'.$this->publicMediaDir.'/'.$filterName.'/'.$mediaFileName;
            if (file_exists($path)) {
                @unlink($path);
            }

            $webpPath = $this->publicDir.'/'.$this->publicMediaDir.'/'.$filterName.'/'.$mediaBase.'.webp';
            if (file_exists($webpPath)) {
                @unlink($webpPath);
            }
        }
    }

    public function ensurePublicSymlink(Media $media): void
    {
        if (! $this->mediaStorage->isLocal()) {
            return;
        }

        $fileName = $media->getFileName();
        $publicPath = $this->publicDir.'/'.$this->publicMediaDir.'/'.$fileName;
        $relativePath = '../../media/'.$fileName;

        if (is_link($publicPath)) {
            return;
        }

        if (file_exists($publicPath)) {
            return;
        }

        $publicMediaPath = $this->publicDir.'/'.$this->publicMediaDir;
        if (! file_exists($publicMediaPath)) {
            new Filesystem()->mkdir($publicMediaPath);
        }

        @symlink($relativePath, $publicPath);
    }

    public function isFilterCacheFresh(Media $media, string $filterName): bool
    {
        $sourcePath = $this->mediaStorage->getLocalPath($media->getFileName());
        if (! file_exists($sourcePath)) {
            return false;
        }

        $sourceTime = filemtime($sourcePath);
        if (false === $sourceTime) {
            return false;
        }

        /** @var string[] $formats */
        $formats = $this->filterSets[$filterName]['formats'] ?? ['original', 'webp'];

        foreach ($formats as $format) {
            $cachePath = 'original' === $format
                ? $this->getFilterPath($media, $filterName)
                : $this->getFilterPath($media, $filterName, $format);

            if (! file_exists($cachePath)) {
                return false;
            }

            $cacheTime = filemtime($cachePath);
            if (false === $cacheTime || $cacheTime < $sourceTime) {
                return false;
            }
        }

        return true;
    }

    public function isAllCacheFresh(Media $media): bool
    {
        return array_all(array_keys($this->filterSets), fn (string $filterName): bool => $this->isFilterCacheFresh($media, $filterName));
    }

    /**
     * @param array<string, array<string, mixed>> $filters
     */
    public function setFilters(array $filters): void
    {
        $this->filterSets = $filters;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFilterSets(): array
    {
        return $this->filterSets;
    }

    public function createFilterDir(string $path): void
    {
        if (! file_exists($path)) {
            new Filesystem()->mkdir($path);
        }
    }
}
