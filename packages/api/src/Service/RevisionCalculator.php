<?php

namespace Pushword\Api\Service;

use Pushword\Core\Entity\Page;

final readonly class RevisionCalculator
{
    /**
     * Stable opaque token derived from the page's identity + last write time.
     * Used as ETag and matched against `If-Match` on PUT/PATCH.
     */
    public function compute(Page $page): string
    {
        $stamp = $page->updatedAt?->format('Y-m-d\TH:i:s.uP') ?? '';

        return sha1($page->host.'|'.$page->getSlug().'|'.$stamp);
    }
}
