<?php

namespace Pushword\Core\Service;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Utils\MediaRenamer;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class MediaConflictResolver
{
    public function __construct(
        private EntityManagerInterface $em,
        private MediaRepository $mediaRepo,
    ) {
    }

    public function resolveConflicts(Media $media): bool
    {
        $renamer = new MediaRenamer();
        $renamed = false;

        for ($i = 0; $i < 10; ++$i) {
            if (! $this->identifiersAreToken($media)) {
                return $renamed;
            }

            $renamer->rename($media);
            $renamed = true;
        }

        throw new Exception('Too much file with similar name `'.$media->getFileName().'`');
    }

    private function identifiersAreToken(Media $media): bool
    {
        $sameName = $this->em->getRepository($media::class)->findOneBy(['alt' => $media->getAlt()]);
        if (null !== $sameName && $media->id !== $sameName->id) {
            return true;
        }

        $mediaString = $this->getMediaString($media);

        return null !== $this->mediaRepo->isFileNameUsed($mediaString, $media->id);
    }

    private function getMediaString(Media $media): string
    {
        if ('' !== $media->getFileName()) {
            return $media->getFileName();
        }

        $mediaFile = $media->getMediaFile();
        $extension = $mediaFile instanceof UploadedFile
            ? (string) $mediaFile->guessExtension() : '';

        return $media->getAlt().('' !== $extension ? '.'.$extension : '');
    }
}
