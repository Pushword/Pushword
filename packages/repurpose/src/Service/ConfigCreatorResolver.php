<?php

namespace Pushword\Repurpose\Service;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Repurpose\Model\Carousel;
use Pushword\Repurpose\Model\Creator;

/**
 * Default creator resolver: reads a keyed `repurpose_creators` map from the site
 * config and resolves the spec's `creator` key against it, falling back to the
 * brand (the site name) so a carousel that never names a creator still gets a
 * correct byline.
 *
 * A downstream app that has richer author data (an avatar, a handle, a
 * page→author link) binds its own {@see CreatorResolverInterface} implementation.
 */
final readonly class ConfigCreatorResolver implements CreatorResolverInterface
{
    public function __construct(
        private SiteRegistry $apps,
    ) {
    }

    public function resolve(Carousel $carousel, string $host): ?Creator
    {
        if ('none' === $carousel->creatorOnSlides) {
            return null;
        }

        $app = $this->apps->getApp($host);
        /** @var array<string, array<string, mixed>> $creators */
        $creators = $app->getArray('repurpose_creators');

        if (null !== $carousel->creator && isset($creators[$carousel->creator])) {
            return $this->fromEntry($creators[$carousel->creator]);
        }

        // Brand fallback: the site name, no avatar.
        $name = $app->getStr('name');

        return '' === $name ? null : new Creator($name, type: 'business');
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function fromEntry(array $entry): Creator
    {
        return new Creator(
            name: \is_string($entry['name'] ?? null) ? $entry['name'] : '',
            role: \is_string($entry['role'] ?? null) ? $entry['role'] : (\is_string($entry['handle'] ?? null) ? $entry['handle'] : null),
            avatar: \is_string($entry['avatar'] ?? null) ? $entry['avatar'] : null,
            type: 'business' === ($entry['type'] ?? null) ? 'business' : 'personal',
        );
    }
}
