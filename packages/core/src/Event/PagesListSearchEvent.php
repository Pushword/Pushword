<?php

namespace Pushword\Core\Event;

use Pushword\Core\Entity\Page;
use Symfony\Contracts\EventDispatcher\Event;

final class PagesListSearchEvent extends Event
{
    public const string NAME = PushwordEvents::PAGES_LIST_SEARCH;

    public function __construct(
        private string $search,
        private readonly ?Page $currentPage,
    ) {
    }

    public function getSearch(): string
    {
        return $this->search;
    }

    public function setSearch(string $search): void
    {
        $this->search = $search;
    }

    public function getCurrentPage(): ?Page
    {
        return $this->currentPage;
    }
}
