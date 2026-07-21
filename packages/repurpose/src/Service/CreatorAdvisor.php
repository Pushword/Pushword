<?php

namespace Pushword\Repurpose\Service;

use Pushword\Repurpose\Model\Carousel;

/**
 * Flags a `creator` key no resolver knows: the byline silently falls back to the
 * brand — the same "valid but wrong" trap as a missing font. A warning, never a
 * violation, because the fallback still renders a correct (if unintended) byline.
 *
 * Host-bound (creators are per-site config), so it runs where the host is known:
 * upsert and the studio preview — not the host-less validate endpoint or CLI.
 */
final readonly class CreatorAdvisor
{
    public function __construct(
        private CreatorResolverInterface $resolver,
    ) {
    }

    /**
     * @return list<array{path: string, message: string}>
     */
    public function warnings(Carousel $carousel, string $host): array
    {
        if (! \is_string($carousel->creator) || 'none' === $carousel->creatorOnSlides) {
            return [];
        }

        $known = $this->resolver->available($host);
        if (isset($known[$carousel->creator])) {
            return [];
        }

        $fallback = $this->resolver->resolve($carousel, $host);

        return [[
            'path' => 'creator',
            'message' => \sprintf(
                'Unknown creator "%s" — %s. Known keys: %s. Use one of them, or an inline {name, role?, avatar?} object.',
                $carousel->creator,
                null === $fallback ? 'no byline will be shown' : \sprintf('the brand byline "%s" will be shown instead', $fallback->name),
                [] === $known ? '(none configured)' : implode(', ', array_keys($known)),
            ),
        ]];
    }
}
