<?php

declare(strict_types=1);

namespace Pushword\Core\Image\License;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The rights an upload of this file will import, readable before it is persisted:
 * MediaLicenseSeedListener decides from it at prePersist, and an app constraint can
 * judge the very same thing at validation, when the media holds none of it yet.
 *
 * @api
 */
final readonly class UploadRightsReader
{
    public function __construct(
        private EmbeddedRightsReader $rightsReader,
        private RequestStack $requestStack,
    ) {
    }

    public function read(File $file): EmbeddedRights
    {
        return EmbeddedRights::merge(
            $this->rightsReader->read($file->getPathname()),
            // Behind the file, never in front of it: the admin scales an image down
            // through a canvas before uploading, which keeps no metadata, so it posts
            // the segments separately. Whatever the stored bytes say still wins.
            $this->rightsReader->readSupplied($this->suppliedMetadata()),
        )->stripGeneratorMarkers();
    }

    private function suppliedMetadata(): string
    {
        $supplied = $this->requestStack->getCurrentRequest()?->request->get('embeddedMetadata');

        return \is_string($supplied) ? $supplied : '';
    }
}
