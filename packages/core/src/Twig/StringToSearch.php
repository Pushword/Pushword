<?php

namespace Pushword\Core\Twig;

use Doctrine\Common\Collections\ArrayCollection;
use Pushword\Core\Entity\PageInterface;

class StringToSearch
{
    /** @var array<int, string|array{0: string, 1: string, 2: string|int|float|int[]}> */
    private array $where = [];

    public function __construct(private readonly string $search, private readonly ?PageInterface $currentPage)
    {
    }

    /** @return array<int, string|array{0: string, 1: string, 2: string|int|float|int[]}> */
    public function retrieve(): array
    {
        if (str_contains($this->search, ' OR ')) {
            $searchToParse = explode(' OR ', $this->search);
            foreach ($searchToParse as $singleSearchToParse) {
                // $where = array_merge($where, $this->stringToSearch($s), ['OR']);
                $this->simpleStringToSearch($singleSearchToParse);
                $this->where[] = 'OR';
            }

            array_pop($this->where);

            return $this->where;
        }

        $this->simpleStringToSearch($this->search);

        return $this->where;
    }

    private function simpleStringToSearch(string $search): void
    {
        if ($this->simpleStringToSearchChildren($search)) {
            return;
        }

        if (str_starts_with($search, 'related:comment:')) {
            $search = '<!--'.substr($search, \strlen('related:comment:')).'-->';

            $this->where[] = ['mainContent', 'LIKE', '%'.$search.'%'];
            $this->where[] = ['id', '<', ($this->currentPage?->getId() ?? 0) + 3];

            if (\in_array('OR', $this->where, true)) {
                throw new \Exception('related:comment + OR not yet supported');
            }

            return;
        }

        if (str_starts_with($search, 'comment:')) {
            $search = '<!--'.substr($search, \strlen('comment:')).'-->';

            $this->where[] = ['mainContent',  'LIKE',  '%'.$search.'%'];

            return;
        }

        if (str_starts_with($search, 'slug:')) {
            $search = substr($search, \strlen('slug:'));

            $this->where[] = ['slug',  'LIKE',  $search];

            return;
        }

        $this->where[] = ['mainContent',  'LIKE',  '%'.$search.'%'];
    }

    private function simpleStringToSearchChildren(string $search): bool
    {
        $searchLowerCased = strtolower($search);
        if ('related' == $searchLowerCased) {
            if (($parentPage = $this->currentPage?->getParentPage()) !== null) {
                $this->where[] = ['parentPage', '=', $parentPage->getId() ?? 0];
                $this->where[] = ['id', '<', ($this->currentPage->getId() ?? 0) + 3];
                if (\in_array('OR', $this->where, true)) {
                    throw new \Exception('related + OR not yet supported');
                }

                return true;
            }

            $this->where[] = ['id', '<', ($this->currentPage?->getId() ?? 0) + 3];

            return true;
        }

        if ('children' == $searchLowerCased) {
            $this->where[] = ['parentPage', '=', $this->currentPage?->getId() ?? 0];

            return true;
        }

        if ('parent_children' == $searchLowerCased) {
            $this->where[] = ['parentPage', '=', $this->currentPage?->getParentPage()?->getId() ?? 0];

            return true;
        }

        if ('children_children' == $searchLowerCased) {
            /** @var int[] */
            $childrenPage = ($this->currentPage?->getChildrenPages() ?? new ArrayCollection([]))->map(static fn ($page): ?int => $page->getId())->toArray();

            $this->where[] = ['parentPage', 'IN', $childrenPage];

            return true;
        }

        return false;
    }
}
