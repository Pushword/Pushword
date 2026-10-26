<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Service;

use League\Flysystem\FilesystemException;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Repurpose\Model\AudioTrack;
use RuntimeException;

final readonly class AudioAssetResolver
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaStorageAdapter $storage,
    ) {
    }

    public function read(AudioTrack $track): string
    {
        // Specs can also come from flat files, so validate before touching storage.
        if (1 !== preg_match(AudioTrack::MEDIA_PATTERN, $track->media)) {
            throw new RuntimeException('Invalid audio filename.');
        }

        $media = $this->mediaRepository->findOneByFileNameOrHistory($track->media);
        if (null === $media || (! str_starts_with($media->getMimeType() ?? '', 'audio/') && 'application/ogg' !== $media->getMimeType())) {
            throw new RuntimeException('Audio media is missing or unsupported: '.$track->media);
        }

        try {
            return $this->storage->read($media->getFileName());
        } catch (FilesystemException $filesystemException) {
            throw new RuntimeException('Audio file could not be read: '.$track->media, 0, $filesystemException);
        }
    }
}
