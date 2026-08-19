<?php

namespace Pushword\Core\Image;

use Exception;
use League\Flysystem\FilesystemException;
use Pushword\Core\Entity\Dimensions;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaCacheStorageAdapter;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Utils\Filepath;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class ImageCacheManager
{
    /**
     * @param array<string, array<string, mixed>> $filterSets
     * @param string                              $publicMediaDir the browser path derivatives are served under
     * @param string                              $mediaCacheDir  the directory they are written to
     */
    public function __construct(
        private array $filterSets,
        private readonly string $publicMediaDir,
        private readonly string $mediaCacheDir,
        private readonly MediaStorageAdapter $mediaStorage,
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly ?MediaCacheStorageAdapter $mediaCacheStorage = null,
    ) {
    }

    public function getFilterPath(Media|string $media, string $filterName, ?string $extension = null, bool $browserPath = false): string
    {
        $key = $this->getFilterKey($media, $filterName, $extension);

        if ($browserPath) {
            return $this->mediaCacheStorage?->getPublicUrl($key) ?? '/'.$this->publicMediaDir.'/'.$key;
        }

        return $this->mediaCacheDir.'/'.$key;
    }

    public function getFilterKey(Media|string $media, string $filterName, ?string $extension = null): string
    {
        $media = $this->fileNameOf($media);
        $fileName = null === $extension ? $media : Filepath::removeExtension($media).'.'.$extension;

        return $filterName.'/'.$fileName;
    }

    private function fileNameOf(Media|string $media): string
    {
        return $media instanceof Media ? $media->getFileName() : Filepath::filename($media);
    }

    #[AsTwigFilter('image')]
    public function getBrowserPath(
        Media|string $media,
        string $filterName = 'default',
        ?string $extension = null,
        bool $checkFileExists = false,
    ): string {
        $mediaFileName = $this->fileNameOf($media);
        if (str_ends_with(strtolower($mediaFileName), '.svg')) {
            return '/'.$this->publicMediaDir.'/'.$mediaFileName;
        }

        if (null !== $extension) {
            return $this->getRoutableBrowserPath($media, $filterName, $extension);
        }

        /** @var string[] $formats */
        $formats = $this->filterSets[$filterName]['formats'] ?? ['webp', 'original'];

        if (\in_array('webp', $formats, true)) {
            if (! $checkFileExists || $this->isFilterFileUsable($media, $filterName, 'webp')) {
                return $this->getRoutableBrowserPath($media, $filterName, 'webp');
            }
        }

        if (\in_array('original', $formats, true)) {
            if (! $checkFileExists || $this->isFilterFileUsable($media, $filterName)) {
                return $this->getRoutableBrowserPath($media, $filterName);
            }
        }

        return $this->getRoutableBrowserPath($media, $filterName);
    }

    /**
     * A cached variant is usable only when it exists AND is non-empty. A 0-byte
     * file (transient encoder failure) must count as missing, so the renderer
     * falls back to a valid variant and the freshness check regenerates it.
     *
     * @phpstan-impure The result changes when a generator publishes the variant.
     */
    public function isFilterFileUsable(Media|string $media, string $filterName, ?string $extension = null): bool
    {
        $key = $this->getFilterKey($media, $filterName, $extension);
        if (null !== $this->mediaCacheStorage) {
            return $this->mediaCacheStorage->fileExists($key) && 0 < $this->mediaCacheStorage->fileSize($key);
        }

        $path = $this->getFilterPath($media, $filterName, $extension);

        return $this->filesystem->exists($path) && 0 < (@filesize($path) ?: 0);
    }

    private function getRoutableBrowserPath(Media|string $media, string $filterName, ?string $extension = null): string
    {
        $key = $this->getFilterKey($media, $filterName, $extension);
        $publicUrl = $this->mediaCacheStorage?->getPublicUrl($key);

        if (null !== $publicUrl) {
            try {
                if ($this->isFilterFileUsable($media, $filterName, $extension)) {
                    return $publicUrl;
                }
            } catch (FilesystemException) {
                // The application route remains able to generate or report the miss.
            }
        }

        return '/'.$this->publicMediaDir.'/'.$key;
    }

    /**
     * Only the ratio is wanted here (the template feeds it to width/height when the
     * media carries no stored dimensions), so the xs derivative answers it cheaply.
     * A cold cache is not an error though — a fresh deploy has one, and so does a
     * test worker — so fall back to the source rather than taking the render down.
     */
    #[AsTwigFunction('image_dimensions')]
    public function getDimensions(Media|string $media): Dimensions
    {
        $path = $this->getFilterPath($media, 'xs');
        $size = @getimagesize($path)
            ?: @getimagesize($this->mediaStorage->getLocalPath($this->fileNameOf($media)));

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
        $mediaFileName = $this->fileNameOf($media);
        $mediaBase = Filepath::removeExtension($mediaFileName);

        foreach (array_keys($this->filterSets) as $filterName) {
            $path = $this->mediaCacheDir.'/'.$filterName.'/'.$mediaFileName;
            $this->filesystem->remove($path);
            $this->removeStored($filterName.'/'.$mediaFileName);

            $webpPath = $this->mediaCacheDir.'/'.$filterName.'/'.$mediaBase.'.webp';
            $this->filesystem->remove($webpPath);
            $this->removeStored($filterName.'/'.$mediaBase.'.webp');
        }

        // Remove root-level public symlink (used by non-image files like PDFs)
        $rootPublicPath = $this->mediaCacheDir.'/'.$mediaFileName;
        if (is_link($rootPublicPath)) {
            $this->filesystem->remove($rootPublicPath);
        }
    }

    public function ensurePublicSymlink(Media $media): void
    {
        if (! $this->mediaStorage->isLocal()) {
            return;
        }

        $fileName = $media->getFileName();
        $publicPath = $this->mediaCacheDir.'/'.$fileName;

        clearstatcache(true, $publicPath);

        if (is_link($publicPath) || $this->filesystem->exists($publicPath)) {
            return;
        }

        $this->createFilterDir($this->mediaCacheDir);

        // Relative, so a deploy that moves the project (releases/<date>/) keeps it valid.
        // Under the default layout this is the '../../media/' it has always been.
        $target = $this->filesystem->makePathRelative(
            \dirname($this->mediaStorage->getLocalPath($fileName)),
            $this->mediaCacheDir,
        ).$fileName;

        try {
            $this->filesystem->symlink($target, $publicPath);
        } catch (IOException) {
            // Race condition: another process created the symlink between our check and creation
        }
    }

    public function isFilterCacheFresh(Media $media, string $filterName): bool
    {
        if (! $this->mediaStorage->fileExists($media->getFileName())) {
            return false;
        }

        $sourceTime = $this->mediaStorage->lastModified($media->getFileName());

        /** @var string[] $formats */
        $formats = $this->filterSets[$filterName]['formats'] ?? ['original', 'webp'];

        foreach ($formats as $format) {
            $extension = 'original' === $format ? null : $format;

            if (! $this->isFilterFileUsable($media, $filterName, $extension)) {
                return false;
            }

            $key = $this->getFilterKey($media, $filterName, $extension);
            $cacheTime = null !== $this->mediaCacheStorage
                ? $this->mediaCacheStorage->lastModified($key)
                : filemtime($this->getFilterPath($media, $filterName, $extension));
            if (false === $cacheTime || $cacheTime < $sourceTime) {
                return false;
            }
        }

        return true;
    }

    public function isAllCacheFresh(Media $media): bool
    {
        // Check thumb first: it's a single webp file (1 stat), so it's the cheapest early-exit
        if (isset($this->filterSets['thumb']) && ! $this->isFilterCacheFresh($media, 'thumb')) {
            return false;
        }

        return array_all(
            array_keys($this->filterSets),
            fn (string $filterName): bool => 'thumb' === $filterName || $this->isFilterCacheFresh($media, $filterName),
        );
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
        $this->filesystem->mkdir($path);
    }

    public function publishFilter(Media|string $media, string $filterName, ?string $extension = null): void
    {
        if (null === $this->mediaCacheStorage) {
            return;
        }

        $key = $this->getFilterKey($media, $filterName, $extension);
        $this->mediaCacheStorage->publish($key, $this->getFilterPath($media, $filterName, $extension));
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public function getSourceDimensions(Media $media): ?array
    {
        $sourcePath = $this->mediaStorage->getLocalPath($media->getFileName());
        if (! $this->filesystem->exists($sourcePath)) {
            return null;
        }

        $size = @getimagesize($sourcePath);

        return false !== $size ? [$size[0], $size[1]] : null;
    }

    /**
     * Returns true when the filter's resize would be a no-op (source already smaller than target).
     */
    public function shouldSkipFilter(string $filterName, int $sourceWidth, int $sourceHeight): bool
    {
        $filterConfig = $this->filterSets[$filterName] ?? null;
        if (null === $filterConfig) {
            return false;
        }

        /** @var array<string, mixed> $filters */
        $filters = $filterConfig['filters'] ?? [];

        foreach ($filters as $method => $parameters) {
            $parameters = \is_array($parameters) ? $parameters : [$parameters];

            if ('scaleDown' === $method) {
                $targetWidth = $parameters[0] ?? null;
                $targetHeight = $parameters[1] ?? null;

                return (null === $targetWidth || $targetWidth >= $sourceWidth)
                    && (null === $targetHeight || $targetHeight >= $sourceHeight);
            }

            if ('coverDown' === $method) {
                $targetWidth = $parameters[0] ?? null;
                $targetHeight = $parameters[1] ?? null;

                return null !== $targetWidth && null !== $targetHeight
                    && $targetWidth >= $sourceWidth && $targetHeight >= $sourceHeight;
            }
        }

        return false;
    }

    /**
     * A filter is chainable when it is a pure proportional width-based scaleDown:
     * deriving a smaller such filter from a larger one is dimensionally lossless,
     * so the progressive-downsizing chain may reuse its output as the next base.
     *
     * coverDown (crops) and height-only scaleDown (null width) are NOT chainable:
     * they change the aspect ratio or need the full source height, so they must
     * derive from the original image — never from an already-shrunk chain base.
     */
    public function isChainableDownscale(string $filterName): bool
    {
        /** @var array<string, mixed> $filters */
        $filters = $this->filterSets[$filterName]['filters'] ?? [];

        if ([] === $filters) {
            return false;
        }

        foreach ($filters as $method => $parameters) {
            $parameters = \is_array($parameters) ? $parameters : [$parameters];

            if ('scaleDown' !== $method || ! isset($parameters[0]) || ! \is_int($parameters[0])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The width a filter downscales to, i.e. the `w` descriptor its derivative deserves
     * in a srcset. Null when the filter caps the height instead (height_300) or is
     * unknown: such an entry goes out without a descriptor rather than with a wrong one.
     */
    #[AsTwigFunction('image_filter_width')]
    public function getFilterTargetWidth(string $filterName): ?int
    {
        $filterConfig = $this->filterSets[$filterName] ?? null;
        if (null === $filterConfig) {
            return null;
        }

        /** @var array<string, mixed> $filters */
        $filters = $filterConfig['filters'] ?? [];

        foreach ($filters as $method => $parameters) {
            $parameters = \is_array($parameters) ? $parameters : [$parameters];

            if (\in_array($method, ['scaleDown', 'coverDown'], true)
                && isset($parameters[0])
                && \is_int($parameters[0])) {
                return $parameters[0];
            }
        }

        return null;
    }

    public function symlinkFilterToDefault(Media $media, string $filterName): void
    {
        /** @var string[] $formats */
        $formats = $this->filterSets[$filterName]['formats'] ?? ['original', 'webp'];

        $filterDir = $this->mediaCacheDir.'/'.$filterName;
        $this->createFilterDir($filterDir);

        foreach ($formats as $format) {
            $extension = 'original' === $format ? null : $format;
            $cachePath = $this->getFilterPath($media, $filterName, $extension);
            $defaultRelative = '../default/'.basename($this->getFilterPath($media, 'default', $extension));

            $this->filesystem->remove($cachePath);
            $this->filesystem->symlink($defaultRelative, $cachePath);
            $this->publishFilter($media, $filterName, $extension);
        }
    }

    private function removeStored(string $path): void
    {
        if (null !== $this->mediaCacheStorage && ! $this->mediaCacheStorage->isLocal()) {
            $this->mediaCacheStorage->delete($path);
        }
    }
}
