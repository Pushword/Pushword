<?php

namespace Pushword\Core\Image;

use Pushword\Core\Entity\Media;
use Pushword\Core\Utils\ImageOptimizer\OptimizerChainFactory;
use Spatie\ImageOptimizer\OptimizerChain;

final readonly class ImageOptimizer
{
    private OptimizerChain $optimizer;

    public function __construct(
        private ImageCacheManager $imageCacheManager,
        private ThumbnailGenerator $thumbnailGenerator,
    ) {
        $this->optimizer = OptimizerChainFactory::create();
    }

    public function optimize(Media $media): void
    {
        foreach (array_keys($this->imageCacheManager->getFilterSets()) as $filterName) {
            $this->optimizeFilter($media, $filterName);
        }
    }

    public function optimizeFilter(Media $media, string $filterName): void
    {
        /** @var string[] $formats */
        $formats = $this->imageCacheManager->getFilterSets()[$filterName]['formats'] ?? ['original', 'webp'];

        $needsGeneration = false;
        if (\in_array('original', $formats, true) && ! file_exists($this->imageCacheManager->getFilterPath($media, $filterName))) {
            $needsGeneration = true;
        }

        if (\in_array('webp', $formats, true) && ! file_exists($this->imageCacheManager->getFilterPath($media, $filterName, 'webp'))) {
            $needsGeneration = true;
        }

        if ($needsGeneration) {
            $this->thumbnailGenerator->generateFilteredCache($media, $filterName);
        }

        if (\in_array('original', $formats, true) && file_exists($this->imageCacheManager->getFilterPath($media, $filterName))) {
            $this->optimizer->optimize($this->imageCacheManager->getFilterPath($media, $filterName));
        }

        if (\in_array('webp', $formats, true) && file_exists($this->imageCacheManager->getFilterPath($media, $filterName, 'webp'))) {
            $this->optimizer->optimize($this->imageCacheManager->getFilterPath($media, $filterName, 'webp'));
        }
    }
}
