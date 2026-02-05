<?php

namespace Pushword\Flat\Importer;

// use iBudasov\Iptc\Manager as Iptc;
use DateTimeInterface;
use Pushword\Core\Entity\Media;
use RuntimeException;

/**
 * Permit to find error in image or link.
 */
trait ImageImporterTrait
{
    /** @return mixed[] */
    abstract private function getData(string $filePath): array;

    /** @return mixed[] */
    abstract private function getDataForFileName(string $fileName): array;

    abstract protected function getMedia(string $media): Media;

    abstract private function hasFileContentChanged(string $filePath, Media $media): bool;

    public function importImage(string $filePath, DateTimeInterface $dateTime): bool
    {
        $fileName = $this->getFilename($filePath);
        $media = $this->getMedia($fileName);

        // Use hash comparison to detect real content changes
        if (false === $this->newMedia && ! $this->hasFileContentChanged($filePath, $media)) {
            ++$this->skippedCount;

            return false; // no update needed
        }

        $this->logger?->info('Importing media `'.$fileName.'` ('.($this->newMedia ? 'new' : $media->id).')');
        ++$this->importedCount;

        $filePath = $this->copyToMediaDir($filePath);

        $this->importImageMediaData($media, $filePath);

        return true;
    }

    /**
     * @return mixed[]
     */
    private function getImageData(string $filePath, ?string $fileName = null): array
    {
        $data = null !== $fileName
            ? $this->getDataForFileName($fileName)
            : $this->getData($filePath);

        if ([] !== $data) {
            return $data;
        }

        // Disabling exif read data bringing too much noise in DB
        if (! str_ends_with($filePath, '.jpg') && ! str_ends_with($filePath, '.jpeg')) {
            return $data;
        }

        // $exifData = @exif_read_data($filePath);
        // if getData === []
        $info = [];
        getimagesize($filePath, $info);

        if (\is_array($info) && isset($info['APP13']) && \is_string($info['APP13'])) {
            $iptc = iptcparse($info['APP13']);
            if (isset($iptc['2#025'])) {
                $data['tags'] = implode(' ', $iptc['2#025']);
            }
        }

        return $data;
    }

    private function importImageMediaData(Media $media, string $filePath, ?string $storeIn = null, ?string $fileName = null): void
    {
        $imgSize = getimagesize($filePath);

        if (false === $imgSize) {
            throw new RuntimeException('Image size could not be determined');
        }

        $media
                ->setProjectDir($this->projectDir)
                ->setStoreIn($storeIn ?? \dirname($filePath))
                ->setMimeType($imgSize['mime'])
                ->setSize((int) filesize($filePath))
                ->setDimensions([$imgSize[0], $imgSize[1]])
                ->resetHash();

        $data = $this->getImageData($filePath, $fileName);

        $this->setData($media, $data);
    }
}
