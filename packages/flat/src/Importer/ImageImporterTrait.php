<?php

namespace Pushword\Flat\Importer;

// use iBudasov\Iptc\Manager as Iptc;
use DateTimeInterface;
use Pushword\Core\Entity\Media;
use RuntimeException;

use function Safe\filesize;
use function Safe\getimagesize;

/**
 * Permit to find error in image or link.
 */
trait ImageImporterTrait
{
    /** @return mixed[] */
    abstract private function getData(string $filePath): array;

    abstract protected function getMedia(string $media): Media;

    public function importImage(string $filePath, DateTimeInterface $dateTime): void
    {
        $fileName = $this->getFilename($filePath);
        $media = $this->getMedia($fileName);

        if (false === $this->newMedia && $media->updatedAt >= $dateTime) {
            ++$this->skippedCount;

            return; // no update needed
        }

        $this->logger?->info('Importing media `'.$fileName.'` ('.($this->newMedia ? 'new' : $media->id).')');
        ++$this->importedCount;

        $filePath = $this->copyToMediaDir($filePath);

        $this->importImageMediaData($media, $filePath);
    }

    /**
     * @return mixed[]
     */
    private function getImageData(string $filePath): array
    {
        $data = $this->getData($filePath);

        if ([] !== $data) {
            return $data;
        }

        // Disabling exif read data bringing too much noise in DB
        if (! str_ends_with($filePath, '.jpg') && ! str_ends_with($filePath, '.jpeg')) {
            return $data;
        }

        // $exifData = @exif_read_data($filePath);
        // if getData === []
        getimagesize($filePath, $info);

        if (isset($info['APP13']) && is_string($info['APP13'])) {
            $iptc = iptcparse($info['APP13']);
            if (isset($iptc['2#025'])) {
                $data['tags'] = implode(' ', $iptc['2#025']);
            }
        }

        return $data;
    }

    private function importImageMediaData(Media $media, string $filePath): void
    {
        $imgSize = getimagesize($filePath);

        if (null === $imgSize) {
            throw new RuntimeException('Image size is null');
        }

        $media
                ->setProjectDir($this->projectDir)
                ->setStoreIn(\dirname($filePath))
                ->setMimeType($imgSize['mime'])
                ->setSize(filesize($filePath))
                ->setDimensions([$imgSize[0], $imgSize[1]]);

        $data = $this->getImageData($filePath); // , $imgSize['mime']);

        $this->setData($media, $data);
    }
}
