<?php

namespace Pushword\Flat\Importer;

use DateTime;
use DateTimeInterface;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Repository\Repository;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Permit to find error in image or link.
 */
class MediaImporter extends AbstractImporter
{
    use ImageImporterTrait;

    protected $mediaDir;

    private bool $newMedia = false;

    /** @required */
    public function setMediaDir(string $mediaDir): self
    {
        $this->mediaDir = $mediaDir;

        return $this;
    }

    public function import(string $filePath, DateTimeInterface $lastEditDatetime): void
    {
        if (! $this->isImage($filePath)) {
            if (str_ends_with($filePath, '.json') && file_exists(substr($filePath, 0, -5))) { // data file
                return;
            }
            $this->importMedia($filePath, $lastEditDatetime);

            return;
        }
        $this->importImage($filePath, $lastEditDatetime);
    }

    private function isImage($filePath): bool
    {
        return false !== getimagesize($filePath);
        //0 !== strpos(finfo_file(finfo_open(\FILEINFO_MIME_TYPE), $filePath), 'image/') || preg_match('/\.webp$/', $filePath);
    }

    public function importMedia(string $filePath, DateTimeInterface $lastEditDatetime): void
    {
        $media = $this->getMedia($this->getFilename($filePath));

        if (1 == 2 && false === $this->newMedia && $media->getUpdatedAt() >= $lastEditDatetime) {
            return; // no update needed
        }

        $filePath = $this->copyToMediaDir($filePath);

        $media
            ->setStoreIn(\dirname($filePath))
            ->setSize(filesize($filePath))
            ->setMimeType(finfo_file(finfo_open(\FILEINFO_MIME_TYPE), $filePath));

        $data = $this->getData($filePath);

        $this->setData($media, $data);
    }

    private function setData(MediaInterface $media, array $data): void
    {
        $media->setCustomProperties([]);

        foreach ($data as $key => $value) {
            $key = self::underscoreToCamelCase($key);

            $setter = 'set'.ucfirst($key);
            if (method_exists($media, $setter)) {
                if (\in_array($key, ['createdAt', 'updatedAt'])) {
                    $value = new DateTime($value['date']);
                }

                $media->$setter($value);

                continue;
            }
            $media->setCustomProperty($key, $value);
        }

        if (true === $this->newMedia) {
            $this->em->persist($media);
        }
    }

    private function getData($filePath): array
    {
        if (! file_exists($filePath.'.json')) {
            return [];
        }

        $jsonData = json_decode(file_get_contents($filePath.'.json'), true);

        return $jsonData ?: [];
    }

    public function getFilename($filePath): string
    {
        return str_replace(\dirname($filePath).'/', '', $filePath);
    }

    private function copyToMediaDir($filePath): string
    {
        $newFilePath = $this->mediaDir.'/'.$this->getFilename($filePath);

        if ($this->mediaDir && $filePath != $newFilePath) {
            (new Filesystem())->copy($filePath, $newFilePath);

            return $newFilePath;
        }

        return $filePath;
    }

    private function getMedia(string $media): ?MediaInterface
    {
        $mediaEntity = Repository::getMediaRepository($this->em, $this->entityClass)->findOneBy(['media' => $media]);
        $this->newMedia = false;

        if (! $mediaEntity) {
            $this->newMedia = true;
            $mediaClass = $this->entityClass;
            $mediaEntity = new $mediaClass();
            $mediaEntity
                ->setMedia($media)
                ->setName($media.' - '.uniqid());
        }

        return $mediaEntity;
    }
}
