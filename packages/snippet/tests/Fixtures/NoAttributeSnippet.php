<?php

namespace Pushword\Snippet\Tests\Fixtures;

use Pushword\Snippet\Component\AbstractSnippetComponent;

final class NoAttributeSnippet extends AbstractSnippetComponent
{
    /**
     * @return array{}
     */
    public function getSchema(): array
    {
        return [];
    }
}
