<?php

declare(strict_types=1);

namespace Pushword\Snippet\Component;

abstract class AbstractSnippetComponent implements SnippetComponentInterface
{
    public function prepareParams(array $params): array
    {
        return $params;
    }
}
