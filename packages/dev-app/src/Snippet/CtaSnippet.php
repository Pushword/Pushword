<?php

namespace App\Snippet;

use Override;
use Pushword\Snippet\Attribute\AsSnippet;
use Pushword\Snippet\Component\AbstractSnippetComponent;

/**
 * Demo component snippet. Replaces the kind of bespoke `ctaBlock()` Twig function
 * downstream sites hand-roll: declare the schema once, get an editor form for free.
 */
#[AsSnippet(name: 'cta', template: 'snippet/cta.html.twig', label: 'Call to action')]
final class CtaSnippet extends AbstractSnippetComponent
{
    /**
     * @return array<string, array<string, string>>
     */
    public function getSchema(): array
    {
        return [
            'title' => ['type' => 'string', 'label' => 'Title'],
            'description' => ['type' => 'text', 'label' => 'Description'],
            'buttonText' => ['type' => 'string', 'label' => 'Button label'],
            'buttonUrl' => ['type' => 'string', 'label' => 'Button URL'],
        ];
    }

    #[Override]
    public function prepareParams(array $params): array
    {
        return $params + [
            'title' => '',
            'description' => '',
            'buttonText' => '',
            'buttonUrl' => '#',
        ];
    }
}
