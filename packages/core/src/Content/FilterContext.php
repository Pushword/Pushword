<?php

namespace Pushword\Core\Content;

use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteConfig;

/**
 * Immutable context passed to filters during content processing.
 */
final readonly class FilterContext
{
    public function __construct(
        public Page $page,
        public string $property,
        public SiteConfig $site,
        private ContentPipeline $pipeline,
    ) {
    }

    /**
     * Apply sub-filters within the current pipeline (e.g. twig filter inside markdown).
     *
     * @param string[] $filters
     */
    public function applySubFilters(bool|float|int|string|null $value, array $filters): mixed
    {
        return $this->pipeline->applyFilters($value, $filters, $this->property);
    }
}
