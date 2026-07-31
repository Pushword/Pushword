<?php

namespace Pushword\Core\Utils;

use Pushword\Core\Entity\Page;
use Pushword\Core\Query\LegacyArrayRenderer;
use Pushword\Core\Query\Search\PageSearchVocabulary;
use Pushword\Core\Query\Search\SearchException;
use Pushword\Core\Query\Search\SearchParser;

/**
 * @see packages/docs/content/pages-list.md
 * @deprecated parse with {@see SearchParser} and compile the tree; this only
 *             renders it back into the legacy array form for callers that still
 *             expect one
 */
class StringToDQLCriteria
{
    public function __construct(private readonly string $search, private readonly ?Page $currentPage)
    {
    }

    /**
     * @return array<mixed>
     *
     * @throws SearchException
     */
    public function retrieve(): array
    {
        return new LegacyArrayRenderer()->render(
            new SearchParser(new PageSearchVocabulary($this->currentPage))->parse($this->search),
        );
    }
}
