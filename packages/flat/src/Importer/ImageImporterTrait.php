<?php

namespace Pushword\Flat\Importer;

use DateTimeInterface;
//use iBudasov\Iptc\Manager as Iptc;
use Pushword\Core\Entity\MediaInterface;

/**
 * Permit to find error in image or link.
 */
trait ImageImporterTrait
{
    public function importImage(string $filePath, DateTimeInterface $lastEditDatetime)
    {
        $media = $this->getMedia($this->getFilename($filePath));

        if (false === $this->newMedia && $media->getUpdatedAt() >= $lastEditDatetime) {
            return; // no update needed
        }

        $filePath = $this->copyToMediaDir($filePath);

        $this->importImageMediaData($media, $filePath);
    }

    private function getImageData(string $filePath): array
    {
        $data = [];

        /*
        if ('image/jpeg' == $mime) {
            $manager = Iptc::create();
            $manager->loadFile($filePath);
            $data = array_merge($data, $manager->getTags());
        }*/

        $reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_NATIVE);
        $exif = $reader->read($filePath);
        if ($exif) {
            $data = array_merge($data, $exif->getData());
        }

        $data = array_merge($data, $this->getData($filePath));

        return $data;
    }

    private function importImageMediaData(MediaInterface $media, string $filePath): void
    {
        $imgSize = getimagesize($filePath);

        $media
                ->setStoreIn(\dirname($filePath))
                ->setMimeType($imgSize['mime'])
                ->setSize(filesize($filePath))
                ->setDimensions([$imgSize[0], $imgSize[1]]);

        $data = $this->getImageData($filePath); //, $imgSize['mime']);

        $this->setData($media, $data);
    }
}
