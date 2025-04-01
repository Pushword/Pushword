<?php

namespace Pushword\Flat\Importer;

// use iBudasov\Iptc\Manager as Iptc;
use DateTimeInterface;
use Pushword\Core\Entity\Media;

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
        $media = $this->getMedia($this->getFilename($filePath));

        if (false === $this->newMedia && $media->getUpdatedAt() >= $dateTime) {
            return; // no update needed
        }

        $filePath = $this->copyToMediaDir($filePath);

        $this->importImageMediaData($media, $filePath);
    }

    /**
     * @return mixed[]
     */
    private function getImageData(string $filePath): array
    {
        /*
        if ('image/jpeg' == $mime) {
            $manager = Iptc::create();
            $manager->loadFile($filePath);
            $data = array_merge($data, $manager->getTags());
        }*/

        $data = @exif_read_data($filePath);
        $data = false === $data ? [] : $data;

        return array_merge($data, $this->getData($filePath));
    }

    private function importImageMediaData(Media $media, string $filePath): void
    {
        $imgSize = getimagesize($filePath);

        if (null === $imgSize) {
            throw new \RuntimeException('Image size is null');
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
