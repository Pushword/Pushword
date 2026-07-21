<?php

namespace Pushword\Repurpose\Model;

/**
 * A resolved creator byline shown on the intro/outro slides: a personal author or
 * the brand. Produced by a {@see \Pushword\Repurpose\Service\CreatorResolverInterface}
 * from the spec's `creator` key, the page author or the site itself.
 */
final readonly class Creator
{
    public function __construct(
        public string $name,
        public ?string $role = null,
        public ?string $avatar = null,
        public string $type = 'personal',
    ) {
    }
}
