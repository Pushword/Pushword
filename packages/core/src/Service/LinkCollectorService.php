<?php

declare(strict_types=1);

namespace Pushword\Core\Service;

use Pushword\Core\Entity\Page;
use Symfony\Contracts\Service\ResetInterface;

final class LinkCollectorService implements ResetInterface
{
    /** @var array<string, true> */
    private array $registeredSlugs = [];

    public function registerSlug(string $slug): void
    {
        $this->registeredSlugs[$slug] = true;
    }

    public function register(Page $page): void
    {
        $this->registeredSlugs[$page->slug] = true;
    }

    /**
     * @param Page[] $pages
     */
    public function registerAll(array $pages): void
    {
        foreach ($pages as $page) {
            $this->registeredSlugs[$page->slug] = true;
        }
    }

    public function isRegistered(Page $page): bool
    {
        return isset($this->registeredSlugs[$page->slug]);
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
            fn (Page $page): bool => ! isset($this->registeredSlugs[$page->slug])
        ));
    }

    /**
     * Worker-mode safety (kernel.reset): the collected slugs are request-scoped, so
     * one render's links must never filter the next one's listings. The static
     * generator renders every exported page in-process, without dispatching
     * kernel.request, so {@see \Pushword\Core\EventListener\LinkCollectorResetListener}
     * never fires there and this is the only reset a whole export run gets.
     */
    public function reset(): void
    {
        $this->registeredSlugs = [];
    }
}
