<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Service;

use League\Flysystem\FilesystemException;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Repurpose\Model\AudioTrack;

final readonly class AudioAssetResolver
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaStorageAdapter $storage,
    ) {
    }

    /**
     * The track's file, streamed so a long recording never sits in PHP memory.
     *
     * @param string $name the track's key in the spec's `audio` object
     *
     * @return resource
     *
     * @throws AudioAssetException when the file name, the media or the stored file is unusable
     */
    public function stream(string $name, AudioTrack $track): mixed
    {
        $path = 'audio.'.$name.'.media';
        // Specs can also come from flat files, so validate before touching storage.
        if (1 !== preg_match(AudioTrack::MEDIA_PATTERN, $track->media)) {
            throw new AudioAssetException('Invalid audio filename.', $path);
        }

        $media = $this->mediaRepository->findOneByFileNameOrHistory($track->media);
        if (null === $media || (! str_starts_with($media->getMimeType() ?? '', 'audio/') && 'application/ogg' !== $media->getMimeType())) {
            throw new AudioAssetException('Audio media is missing or unsupported: '.$track->media, $path);
        }

        try {
            return $this->storage->readStream($media->getFileName());
        } catch (FilesystemException $filesystemException) {
            throw new AudioAssetException('Audio file could not be read: '.$track->media, $path, $filesystemException);
        }
    }
}
