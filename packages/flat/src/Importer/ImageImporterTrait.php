<?php

namespace Pushword\Flat\Importer;

// use iBudasov\Iptc\Manager as Iptc;
use Pushword\Core\Entity\MediaInterface;

/**
 * Permit to find error in image or link.
 */
trait ImageImporterTrait
{
    abstract protected function getMedia(string $media): MediaInterface;

    public function importImage(string $filePath, \DateTimeInterface $dateTime): void
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
        $data = [];

        /*
        if ('image/jpeg' == $mime) {
            $manager = Iptc::create();
            $manager->loadFile($filePath);
            $data = array_merge($data, $manager->getTags());
        }*/

        $reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_NATIVE);
        $exif = $reader->read($filePath);
        if ($exif) { // @phpstan-ignore-line
            $data = array_merge($data, $exif->getData());
        }

        return array_merge($data, $this->getData($filePath));
    }

    private function importImageMediaData(MediaInterface $media, string $filePath): void
    {
        $imgSize = \Safe\getimagesize($filePath);

        $media
                ->setProjectDir($this->projectDir)
                ->setStoreIn(\dirname($filePath))
                ->setMimeType($imgSize['mime'])
                ->setSize(\Safe\filesize($filePath))
                ->setDimensions([$imgSize[0], $imgSize[1]]);

        $data = $this->getImageData($filePath); // , $imgSize['mime']);

        $this->setData($media, $data);
    }
}
