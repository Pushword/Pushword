<?php

namespace Pushword\Repurpose\Service;

use Pushword\Repurpose\Model\Carousel;
use Pushword\Repurpose\Model\Creator;

/**
 * Resolves the creator byline for a carousel. The default implementation reads a
 * keyed `repurpose_creators` site config and falls back to the brand; a downstream
 * app can bind its own (e.g. reading its author entity via the page's `publishedBy`)
 * without the package depending on that entity.
 */
interface CreatorResolverInterface
{
    /**
     * @param string $host the host the carousel belongs to (for per-site config and page lookup)
     */
    public function resolve(Carousel $carousel, string $host): ?Creator;

    /**
     * Every creator key `resolve()` accepts for this host, mapped to its display
     * name. Both the studio's creator picker and the unknown-key warning read
     * this, so it must stay exhaustive — a key resolve() honours but available()
     * omits would be flagged as unknown.
     *
     * @return array<string, string> key => display name
     */
    public function available(string $host): array;
}
