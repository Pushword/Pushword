<?php

declare(strict_types=1);

namespace Pushword\Core\Service;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Utils\MediaRenamer;

final readonly class MediaConflictResolver
{
    /**
     * Safety bound on the number of `name (N)` suffixes we try before giving up,
     * high enough to absorb large series of similarly named files.
     */
    private const int MAX_RENAME_ATTEMPTS = 100;

    public function __construct(
        private EntityManagerInterface $em,
        private MediaRepository $mediaRepo,
        private MediaStorageAdapter $mediaStorage,
    ) {
    }

    public function resolveConflicts(Media $media): bool
    {
        $renamer = new MediaRenamer();
        $renamed = false;

        for ($i = 0; $i < self::MAX_RENAME_ATTEMPTS; ++$i) {
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
        $sameName = $this->em->getRepository(Media::class)->findOneBy(['alt' => $media->getAlt()]);
        if (null !== $sameName && $media->id !== $sameName->id) {
            return true;
        }

        // An upload is written over whatever sits at its name, so a new media must not get
        // a name the disk already has, whoever claims the file. (A stored media renamed
        // onto a file is refused by MediaStorageListener instead.)
        if (null === $media->id && $this->mediaStorage->fileExists($media->getFileName())) {
            return true;
        }

        return null !== $this->mediaRepo->isFileNameUsed($media->getFileName(), $media->id);
    }
}
