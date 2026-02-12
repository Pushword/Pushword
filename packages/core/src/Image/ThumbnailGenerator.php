<?php

namespace Pushword\Core\Image;

use Exception;
use Intervention\Image\Image;
use Intervention\Image\Interfaces\ImageInterface;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;

final class ThumbnailGenerator
{
    private ?ImageInterface $lastThumb = null;

    public function __construct(
        private readonly ImageReader $imageReader,
        private readonly ImageEncoder $imageEncoder,
        private readonly ImageCacheManager $imageCacheManager,
        private readonly BackgroundTaskDispatcherInterface $backgroundTaskDispatcher,
        private readonly MediaStorageAdapter $mediaStorage,
    ) {
    }

    public function getImageReader(): ImageReader
    {
        return $this->imageReader;
    }

    /**
     * @return bool True if cache was generated, false if skipped (already fresh)
     */
    public function generateCache(Media $media, bool $force = false): bool
    {
        if (! $force && $this->imageCacheManager->isAllCacheFresh($media)) {
            return false;
        }

        $sourceDimensions = $this->imageCacheManager->getSourceDimensions($media);

        $filtersToProcess = [];
        $filtersToSymlink = [];

        foreach (array_keys($this->imageCacheManager->getFilterSets()) as $filterName) {
            if (! $force && $this->imageCacheManager->isFilterCacheFresh($media, $filterName)) {
                continue;
            }

            if ('default' !== $filterName
                && null !== $sourceDimensions
                && $this->imageCacheManager->shouldSkipFilter($filterName, $sourceDimensions[0], $sourceDimensions[1])) {
                $filtersToSymlink[] = $filterName;

                continue;
            }

            $filtersToProcess[] = $filterName;
        }

        if ([] === $filtersToProcess && [] === $filtersToSymlink) {
            return false;
        }

        // Sort filters: largest target width first, non-width filters at the end
        usort($filtersToProcess, function (string $a, string $b): int {
            if ('default' === $a) {
                return -1;
            }

            if ('default' === $b) {
                return 1;
            }

            $widthA = $this->imageCacheManager->getFilterTargetWidth($a) ?? 0;
            $widthB = $this->imageCacheManager->getFilterTargetWidth($b) ?? 0;

            return $widthB <=> $widthA;
        });

        $image = $this->readAndUpdateMetadata($media);
        $generated = false;

        if ([] !== $filtersToProcess) {
            $currentImage = $image;

            foreach ($filtersToProcess as $filterName) {
                $workImage = clone $currentImage;
                $resultImage = $this->generateFilteredCache($media, $filterName, $workImage, skipClone: true);
                $generated = true;

                if ('thumb' === $filterName) {
                    $this->lastThumb = $resultImage;
                }

                // Progressive downsizing: use result as base for next smaller filter
                if (null !== $this->imageCacheManager->getFilterTargetWidth($filterName)) {
                    unset($currentImage);
                    $currentImage = $resultImage;
                }
            }

            unset($currentImage);
        }

        foreach ($filtersToSymlink as $filterName) {
            $this->imageCacheManager->symlinkFilterToDefault($media, $filterName);
            $generated = true;
        }

        if ($generated) {
            $this->runBackgroundOptimization($media->getFileName());
        }

        return $generated;
    }

    /**
     * @param array<string, mixed>|string $filter
     */
    public function generateFilteredCache(
        Media|string $media,
        array|string $filter,
        ?ImageInterface $originalImage = null,
        bool $skipClone = false,
    ): ImageInterface {
        if (\is_array($filter)) {
            $filterName = array_keys($filter)[0];
            $filters = $filter;
        } else {
            $filters = $this->imageCacheManager->getFilterSets();
            $filterName = $filter;
        }

        $image = match (true) {
            null === $originalImage => $this->imageReader->read($media),
            $skipClone => $originalImage,
            default => 'default' === $filterName ? $originalImage : clone $originalImage,
        };

        foreach ($filters[$filterName]['filters'] as $filter => $parameters) { // @phpstan-ignore-line
            $parameters = \is_array($parameters) ? $parameters : [$parameters];
            \call_user_func_array([$image, $filter], $parameters); // @phpstan-ignore-line
        }

        $quality = (int) ($filters[$filterName]['quality'] ?? 90); // @phpstan-ignore-line
        /** @var string[] $formats */
        $formats = $filters[$filterName]['formats'] ?? ['original', 'webp']; // @phpstan-ignore-line

        $this->imageCacheManager->createFilterDir(\dirname($this->imageCacheManager->getFilterPath($media, $filterName)));

        if (\in_array('original', $formats, true)) {
            $outputPath = $this->imageCacheManager->getFilterPath($media, $filterName);
            $this->imageEncoder->encodeOriginal($image, $outputPath, $quality, $media);
        }

        if (\in_array('webp', $formats, true)) {
            $this->imageEncoder->encodeWebp(
                $image,
                $this->imageCacheManager->getFilterPath($media, $filterName, 'webp'),
                $quality,
            );
        }

        return $image;
    }

    /**
     * Generate only a quick preview for admin by copying original file.
     *
     * @return ImageInterface|null Returns null if image format is not supported by current driver
     */
    public function generateQuickThumb(Media $media): ?ImageInterface
    {
        $this->copyOriginalToFilter($media, 'md');

        try {
            return $this->readAndUpdateMetadata($media);
        } catch (Exception) {
            return null;
        }
    }

    public function runBackgroundCacheGeneration(string $fileName): void
    {
        $this->backgroundTaskDispatcher->dispatch(
            'image-cache-'.md5($fileName),
            ['php', 'bin/console', 'pw:image:cache', $fileName],
            'pw:image:cache',
        );
    }

    public function getLastThumb(): ?ImageInterface
    {
        return $this->lastThumb;
    }

    private function runBackgroundOptimization(string $fileName): void
    {
        $this->backgroundTaskDispatcher->dispatch(
            'image-optimize-'.md5($fileName),
            ['php', 'bin/console', 'pw:image:optimize', $fileName],
            'pw:image:optimize',
        );
    }

    private function copyOriginalToFilter(Media $media, string $filterName): void
    {
        $sourcePath = $this->mediaStorage->getLocalPath($media->getFileName());
        $destPath = $this->imageCacheManager->getFilterPath($media, $filterName);

        $this->imageCacheManager->createFilterDir(\dirname($destPath));

        if (file_exists($sourcePath)) {
            copy($sourcePath, $destPath);
        }
    }

    private function readAndUpdateMetadata(Media $media): ImageInterface
    {
        $image = $this->imageReader->read($media);
        $this->updateMainColor($media, $image);
        $media->setDimensions([$image->width(), $image->height()]);

        return $image;
    }

    private function updateMainColor(Media $media, ?ImageInterface $image = null): void
    {
        if (! $image instanceof Image) {
            return;
        }

        $imageForPalette = clone $image;
        $color = $imageForPalette->pickColor(0, 0)->toHex('#');

        $media->setMainColor($color);
    }
}
