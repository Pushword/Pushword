<?php

namespace Pushword\Core\Utils;

use Doctrine\Common\Collections\ArrayCollection;
use Pushword\Core\Entity\Page;

/**
 * @see packages/docs/content/pages-list.md
 */
class StringToDQLCriteria
{
    /** @var array<int, string|array{0: string, 1: string, 2: string|int|float|int[]}|array{0: string, 1: string, 2: string|int|float|int[]}[]> */
    private array $where = [];

    public function __construct(private readonly string $search, private readonly ?Page $currentPage)
    {
    }

    /** @return array<int, string|array{0: string, 1: string, 2: string|int|float|int[]}|array{0: string, 1: string, 2: string|int|float|int[]}[]> */
    public function retrieve(): array
    {
        foreach (['OR', 'AND'] as $operator) {
            if (str_contains($this->search, ' '.$operator.' ')) {
                $searchToParse = explode(' '.$operator.' ', $this->search);
                foreach ($searchToParse as $singleSearchToParse) {
                    // $where = array_merge($where, $this->stringToSearch($s), ['OR']);
                    $this->simpleStringToSearch($singleSearchToParse);
                    $this->where[] = $operator;
                }

                array_pop($this->where);

                return $this->where;
            }
        }

        $this->simpleStringToSearch($this->search);

        return $this->where;
    }

    private function simpleStringToSearch(string $search): void
    {
        $search = trim($search);

        if ($this->simpleStringToSearchChildren($search)) {
            return;
        }

        if (str_starts_with($search, 'related:comment:')) {
            $search = '<!--'.substr($search, \strlen('related:comment:')).'-->';

            $this->where[] = [
                ['mainContent', 'LIKE', '%'.$search.'%'],
                ['id', '<', ($this->currentPage?->id ?? 0) + 3], // @phpstan-ignore nullsafe.neverNull
            ];

            return;
        }

        if (str_starts_with($search, 'comment:')) {
            $search = '<!--'.substr($search, \strlen('comment:')).'-->';

            $this->where[] = ['mainContent',  'LIKE',  '%'.$search.'%'];

            return;
        }

        if (($searchTitle = str_starts_with($search, 'title:')) || str_starts_with($search, 'content:')) {
            $search = substr($search, $searchTitle ? \strlen('title:') : \strlen('content:'));

            $where = [['h1',  'LIKE',  '%'.$search.'%'], 'OR', ['title',  'LIKE',  '%'.$search.'%']];

            if (! $searchTitle) {
                $where[] = 'OR';
                $where[] = ['mainContent',  'LIKE',  '%'.$search.'%'];
            }

            $this->where[] = $where;

            return;
        }

        if (str_starts_with($search, 'slug:') || str_starts_with($search, 'page:')) {
            $search = substr($search, \strlen('slug:'));

            $this->where[] = ['slug',  'LIKE',  $search];

            return;
        }

        $this->where[] = ['tags',  'LIKE',  '%"'.$search.'"%'];
        // $this->where[] = ['mainContent',  'LIKE',  '%'.$search.'%'];
    }

    private function simpleStringToSearchChildren(string $search): bool
    {
        $searchLowerCased = strtolower($search);
        if ('related' === $searchLowerCased) {
            $currentPage = $this->currentPage;
            if (null !== $currentPage && ($parentPage = $currentPage->getParentPage()) !== null) {
                $this->where[] = [
                    ['parentPage', '=', $parentPage->id ?? 0],
                    ['id', '<', ($currentPage->id ?? 0) + 3],
                ];

                return true;
            }

            $this->where[] = ['id', '<', ($currentPage?->id ?? 0) + 3]; // @phpstan-ignore nullsafe.neverNull

            return true;
        }

        if ('children' === $searchLowerCased) {
            $this->where[] = ['parentPage', '=', $this->currentPage?->id ?? 0]; // @phpstan-ignore nullsafe.neverNull

            return true;
        }

        if (\in_array($searchLowerCased, ['parent_children', 'sisters'], true)) {
            $this->where[] = ['parentPage', '=', $this->currentPage?->getParentPage()?->id ?? 0]; // @phpstan-ignore nullsafe.neverNull

            return true;
        }

        if (\in_array($searchLowerCased, ['children_children', 'grandchildren'], true)) {
            $childrenPage = ($this->currentPage?->getChildrenPages() ?? new ArrayCollection([]))
                ->map(static fn ($page): int => $page->id ?? 0)->toArray();

            $this->where[] = ['parentPage', 'IN', $childrenPage];

            return true;
        }

        return false;
    }
}
