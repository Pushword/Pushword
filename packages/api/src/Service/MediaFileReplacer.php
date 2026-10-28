<?php

declare(strict_types=1);

namespace Pushword\Api\Service;

use DateTime;
use Pushword\Core\Entity\Embeddable\ImageData;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Service\PdfOptimizer;

use function Safe\fclose;
use function Safe\fopen;
use function Safe\sha1_file;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class MediaFileReplacer
{
    public function __construct(
        private MediaStorageAdapter $mediaStorage,
        private ImageCacheManager $imageCacheManager,
        private ImageCacheGenerator $imageCacheGenerator,
        private PdfOptimizer $pdfOptimizer,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /** The caller validates the upload and flushes the updated entity afterwards. */
    public function replace(Media $media, UploadedFile $file): void
    {
        $fileName = $media->getFileName();
        $hash = sha1_file($file->getPathname(), true);
        $stream = fopen($file->getPathname(), 'rb');

        try {
            $this->mediaStorage->writeStream($fileName, $stream);
        } finally {
            fclose($stream);
        }

        // Remote masters have a local download cache used by image processing.
        if (! $this->mediaStorage->isLocal()) {
            $this->filesystem->copy($file->getPathname(), $this->mediaStorage->getLocalPath($fileName), true);
        }

        $media->size = $file->getSize();
        $media->setHash($hash)->setOriginalHash($hash);
        $media->updatedAt = new DateTime();

        $this->imageCacheManager->remove($media);
        if ($media->isImage()) {
            // A large image can defer decoding to the background worker. Its header
            // still gives us fresh dimensions; never retain the previous main color.
            $media->imageData = new ImageData();
            $media->setDimensions($this->imageCacheManager->getSourceDimensions($media));
            $this->imageCacheGenerator->generateQuickPreview($media);
            $this->imageCacheGenerator->runBackgroundCacheGeneration($fileName);

            return;
        }

        $this->imageCacheManager->ensurePublicSymlink($media);
        if ('application/pdf' === $media->getMimeType()) {
            $this->pdfOptimizer->runBackgroundOptimization($fileName);
        }
    }
}
