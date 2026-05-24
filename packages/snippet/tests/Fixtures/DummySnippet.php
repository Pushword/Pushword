<?php

namespace Pushword\Snippet\Tests\Fixtures;

use Pushword\Snippet\Attribute\AsSnippet;
use Pushword\Snippet\Component\AbstractSnippetComponent;

#[AsSnippet(name: 'dummy', template: 'dummy.html.twig', label: 'Dummy')]
final class DummySnippet extends AbstractSnippetComponent
{
    /**
     * @return array<string, array<string, string>>
     */
    public function getSchema(): array
    {
        return ['title' => ['type' => 'string']];
    }
}
