<?php

namespace Pushword\LinkImprover;

use Pushword\Core\Entity\Page;
use Symfony\Contracts\Service\ResetInterface;

/**
 * What the improver inserted during this process, per page — the reporting
 * surfaces (`pw:link-improver` and the admin panel) read it after rendering.
 *
 * Alongside the links it keeps the numbers that decided them: a page that
 * gained nothing did so for a reason, and only the render knows which.
 *
 * @phpstan-type RenderStats array{wordCount: int, cap: int, existingLinks: int}
 */
final class AddedLinksRegistry implements ResetInterface
{
    /** @var array<string, list<array{anchor: string, url: string}>> */
    private array $added = [];

    /** @var array<string, RenderStats> */
    private array $stats = [];

    public function record(Page $page, string $anchor, string $url): void
    {
        $this->added[$this->key($page)][] = ['anchor' => $anchor, 'url' => $url];
    }

    /**
     * @param int $cap           the total of in-content links the page was allowed to end with
     * @param int $existingLinks the links the content already carried, before the improver
     */
    public function recordRender(Page $page, int $wordCount, int $cap, int $existingLinks): void
    {
        $this->stats[$this->key($page)] = [
            'wordCount' => $wordCount,
            'cap' => $cap,
            'existingLinks' => $existingLinks,
        ];
    }

    /**
     * @return list<array{anchor: string, url: string}>
     */
    public function forPage(Page $page): array
    {
        return $this->added[$this->key($page)] ?? [];
    }

    /**
     * Null when the improver did not run on this page: the app has not opted
     * in, or the host offered no linkable page at all.
     *
     * @return RenderStats|null
     */
    public function statsForPage(Page $page): ?array
    {
        return $this->stats[$this->key($page)] ?? null;
    }

    private function key(Page $page): string
    {
        return $page->host.'/'.$page->slug;
    }

    public function reset(): void
    {
        $this->added = [];
        $this->stats = [];
    }
}
