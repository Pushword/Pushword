<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Block;

final readonly class BlockContent
{
    /** @param array<string, mixed> $parameters */
    public function __construct(public string $reference, public string $label, public string $markdown, public array $parameters = [])
    {
    }

    /** @return array{provider: string, reference: string, label: string, markdown: string, parameters: array<string, mixed>} */
    public function toArray(string $provider): array
    {
        return ['provider' => $provider, 'reference' => $this->reference, 'label' => $this->label, 'markdown' => $this->markdown, 'parameters' => $this->parameters];
    }
}
