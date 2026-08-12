<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\Typographer;
use Pushword\Core\Site\SiteRegistry;

/**
 * Render-time typography: sources stay plain ASCII (git-friendly, greppable),
 * the typographic characters only exist in the rendered output.
 * Disable per page with `filter_typography: 0`.
 */
#[AsFilter]
class Typography implements FilterInterface
{
    public function __construct(
        private readonly Typographer $typographer,
        private readonly SiteRegistry $apps,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        if (! \is_string($propertyValue) || '' === $propertyValue) {
            return $propertyValue;
        }

        $locale = '' !== $page->locale ? $page->locale : $this->apps->get($page->host)->locale;

        return $this->typographer->fix($propertyValue, $locale);
    }
}
