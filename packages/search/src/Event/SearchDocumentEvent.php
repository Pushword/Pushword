<?php

namespace Pushword\Search\Event;

use Pushword\Core\Entity\Page;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per page while building its Loupe document, after the core
 * fields are set. Listeners append custom attributes (e.g. product metadata)
 * to the indexed document; declare those keys in `filterable_attributes` or
 * `searchable_attributes` to filter, facet or rank on them.
 */
final class SearchDocumentEvent extends Event
{
    /**
     * @param array<string, mixed> $document
     */
    public function __construct(
        private array $document,
        private readonly Page $page,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDocument(): array
    {
        return $this->document;
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->document[$name] = $value;
    }

    public function getPage(): Page
    {
        return $this->page;
    }
}
