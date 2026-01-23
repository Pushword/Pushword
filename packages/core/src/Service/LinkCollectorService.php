<?php

namespace Pushword\Core\Service;

use Pushword\Core\Entity\Page;

final class LinkCollectorService
{
    /** @var array<string, true> */
    private array $registeredSlugs = [];

    public function registerSlug(string $slug): void
    {
        $this->registeredSlugs[$slug] = true;
    }

    public function register(Page $page): void
    {
        $this->registeredSlugs[$page->getSlug()] = true;
    }

    public function isSlugRegistered(string $slug): bool
    {
        return isset($this->registeredSlugs[$slug]);
    }

    /**
     * @return array<string, true>
     */
    public function getRegisteredSlugs(): array
    {
        return $this->registeredSlugs;
    }

    /**
     * @param Page[] $pages
     *
     * @return Page[]
     */
    public function excludeRegistered(array $pages): array
    {
        return array_values(array_filter(
            $pages,
            fn (Page $page): bool => ! isset($this->registeredSlugs[$page->getSlug()])
        ));
    }

    public function reset(): void
    {
        $this->registeredSlugs = [];
    }
}
