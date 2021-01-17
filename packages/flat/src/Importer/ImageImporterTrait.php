<?php

namespace Pushword\Flat\Importer;

use DateTime;
use DateTimeInterface;
use iBudasov\Iptc\Manager as Iptc;
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

        $this->copyToMediaDir($filePath);

        $this->importImageMediaData($media, $filePath);
    }

    private function getImageData(string $filePath, string $mime): array
    {
        $data = [];

        if ('image/jpeg' == $mime) {
            $manager = Iptc::create();
            $manager->loadFile($filePath);
            $data = array_merge($data, $manager->getTags());
        }

        $reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_NATIVE);
        $exif = $reader->read($filePath);
        if ($exif) {
            $data = array_merge($data, $exif->getData());
        }

        if (file_exists($filePath.'.json')) {
            $jsonData = json_decode(file_get_contents($filePath.'.json'), true);
            if ($jsonData) {
                $data = array_merge($data, $jsonData);
            }
        }

        return $data;
    }

    private function importImageMediaData(MediaInterface $media, string $filePath): void
    {
        $imgSize = getimagesize($filePath);
        $media
                ->setRelativeDir('media')
                ->setMimeType($imgSize['mime'])
                ->setSize(filesize($filePath))
                ->setDimensions([$imgSize[0], $imgSize[1]]);

        $data = $this->getImageData($filePath, $imgSize['mime']);

        $media->setCustomProperties([]);

        foreach ($data as $key => $value) {
            $key = self::underscoreToCamelCase($key);

            $setter = 'set'.ucfirst($key);
            if (method_exists($media, $setter)) {
                if (in_array($key, ['createdAt', 'updatedAt']))
                    $value = new DateTime($value);

                $media->$setter($value);

                continue;
            }
            $media->setCustomProperty($key, $value);
        }

        if (true === $this->newMedia) {
            $this->em->persist($media);
        }
    }
}
