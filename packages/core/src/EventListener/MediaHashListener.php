<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaStorageAdapter;

use function Safe\sha1_file;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'prePersist'])]
final readonly class MediaHashListener
{
    public function __construct(
        private MediaStorageAdapter $mediaStorage,
    ) {
    }

    public function prePersist(Media $media): void
    {
        $media->initTimestampableProperties();

        if (null === $media->getMediaFile() && '' !== $media->getFileName()) {
            $localPath = $this->mediaStorage->getLocalPath($media->getFileName());

            // A media can be persisted while its physical file is (temporarily) absent
            // — concurrent deletes, remote storage, pending uploads. Hashing a missing
            // file must not abort the whole flush; leave the hash unset instead.
            if (! is_file($localPath)) {
                return;
            }

            $media->setHash(sha1_file($localPath, true));
        } else {
            $media->setHash();
        }
    }
}
