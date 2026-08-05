<?php

namespace Pushword\LinkImprover;

use Pushword\Core\Entity\Page;
use Symfony\Contracts\Service\ResetInterface;

/**
 * What the improver inserted during this process, per page — the reporting
 * surface `pw:link-improver` reads after rendering each page.
 */
final class AddedLinksRegistry implements ResetInterface
{
    /** @var array<string, list<array{anchor: string, url: string}>> */
    private array $added = [];

    public function record(Page $page, string $anchor, string $url): void
    {
        $this->added[$this->key($page)][] = ['anchor' => $anchor, 'url' => $url];
    }

    /**
     * @return list<array{anchor: string, url: string}>
     */
    public function forPage(Page $page): array
    {
        return $this->added[$this->key($page)] ?? [];
    }

    private function key(Page $page): string
    {
        return $page->host.'/'.$page->slug;
    }

    public function reset(): void
    {
        $this->added = [];
    }
}
