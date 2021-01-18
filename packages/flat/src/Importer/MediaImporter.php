<?php

namespace Pushword\Flat\Importer;

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

    protected string $mediaDir;

    private bool $newMedia = false;

    public function setMediaDir(string $mediaDir): self
    {
        $this->mediaDir = $mediaDir;

        return $this;
    }

    public function import(string $filePath, DateTimeInterface $lastEditDatetime)
    {
        // for now, we import only image TODO
        if (
            0 !== strpos(finfo_file(finfo_open(\FILEINFO_MIME_TYPE), $filePath), 'image/')
            || preg_match('/\.webp$/', $filePath)) {
            return;
        }
        $this->importImage($filePath, $lastEditDatetime);
    }

    public function getFilename($filePath): string
    {
        return str_replace(\dirname($filePath).'/', '', $filePath);
    }

    private function copyToMediaDir($filePath)
    {
        if (! preg_match('@media/default$@', \dirname($filePath))) {
            return;
        }

        $fs = new Filesystem();
        $fs->copy($filePath, $this->mediaDir.'/'.$this->getFilename($filePath));
    }

    private function getMedia($media): ?MediaInterface
    {
        $mediaEnitity = Repository::getMediaRepository($this->em, $this->entityClass)->findOneBy(['media' => $media]);
        $this->newMedia = false;

        if (! $mediaEnitity) {
            $this->newMedia = true;
            $mediaClass = $this->entityClass;
            $mediaEnitity = new $mediaClass();
            $mediaEnitity->setSlug($media)->setMedia($media)->setName($media.' - '.uniqid());
        }

        return $mediaEnitity;
    }
}
